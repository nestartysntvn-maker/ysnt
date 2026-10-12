<?php
namespace App\Filament\Pages;

use App\Filament\Resources\Ambassadors\AmbassadorResource;
use App\Models\Ambassador;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

class Pos extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;
    protected static string|UnitEnum|null $navigationGroup = 'Bán hàng';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationLabel = 'Bán tại quầy (POS)';
    protected static ?string $title = 'Bán tại quầy';
    protected static ?string $slug = 'pos';
    protected string $view = 'filament.pages.pos';

    public string $search = '';
    public ?int $cat = null;
    /** @var array<int,int> variant_id => qty */
    public array $cart = [];
    public string $phone = '';
    public string $customerName = '';
    public string $refCode = '';
    public string $pay = 'cash';
    public ?string $cashGiven = null;
    public ?string $manualDiscount = null;
    public string $discountReason = '';
    public ?int $lastOrderId = null;

    public static function canAccess(): bool { return in_array(auth()->user()?->role, ['admin', 'sales'], true); }
    public function isAdmin(): bool { return auth()->user()?->role === 'admin'; }

    public function getViewData(): array
    {
        $q = ProductVariant::query()->with('product')->whereHas('product', fn ($p) => $p->where('status', 'active'))->where('is_available', true);
        if ($this->cat) $q->whereHas('product', fn ($p) => $p->where('category_id', $this->cat));
        if (trim($this->search) !== '') {
            $s = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($this->search)) . '%';
            $q->where(fn ($w) => $w->where('sku', 'like', $s)->orWhere('name', 'like', $s)->orWhereHas('product', fn ($p) => $p->where('name', 'like', $s)));
        }
        $items = $q->orderBy('product_id')->orderBy('sort_order')->limit(60)->get();
        $lines = $this->lines();
        return ['items' => $items, 'cats' => Category::whereHas('products')->orderBy('sort_order')->get(), 'lines' => $lines,
            'amb' => $this->ambassador(), 'refKnown' => $this->refCode === '' || (bool) $this->ambassador(), 't' => $this->totals($lines),
            'last' => $this->lastOrderId ? Order::find($this->lastOrderId) : null];
    }

    public function lines()
    {
        $vs = ProductVariant::with('product')->whereIn('id', array_keys($this->cart))->get()->keyBy('id');
        return collect($this->cart)->map(fn ($q, $id) => ($v = $vs->get($id)) ? ['v' => $v, 'q' => $q, 't' => (int) $v->list_price * $q] : null)->filter();
    }

    public function ambassador(): ?Ambassador
    {
        $c = strtoupper(trim($this->refCode));
        return $c === '' ? null : Ambassador::where('ref_code', $c)->where('status', 'active')->first();
    }

    public function totals($lines): array
    {
        $sub = (int) $lines->sum('t');
        $policy = $this->ambassador()?->policy();
        $refPct = $policy?->customer_discount_percent !== null ? (float) $policy->customer_discount_percent : 0;
        $refDisc = (int) round($sub * $refPct / 100);
        $manual = $this->isAdmin() ? max(0, min((int) $this->manualDiscount, $sub - $refDisc)) : 0;
        $total = $sub - $refDisc - $manual;
        $cash = (int) preg_replace('/\D/', '', (string) $this->cashGiven);
        return ['sub' => $sub, 'refPct' => $refPct, 'refDisc' => $refDisc, 'manual' => $manual, 'total' => $total, 'cash' => $cash, 'change' => $cash - $total,
            'rate' => $policy?->rate_percent !== null ? (float) $policy->rate_percent : null];
    }

    public function add(int $id): void
    {
        $v = ProductVariant::find($id);
        if (! $v || ! $v->is_available) return;
        $want = ($this->cart[$id] ?? 0) + 1;
        if ($v->stock_qty !== null && $want > $v->stock_qty) { Notification::make()->title("Chỉ còn {$v->stock_qty} {$v->name} trong kho")->warning()->send(); return; }
        $this->cart[$id] = min(99, $want);
        $this->lastOrderId = null;
    }

    public function dec(int $id): void { if (isset($this->cart[$id])) { $this->cart[$id]--; if ($this->cart[$id] < 1) unset($this->cart[$id]); } }
    public function remove(int $id): void { unset($this->cart[$id]); }
    public function clearCart(): void { $this->reset(['cart', 'phone', 'customerName', 'refCode', 'cashGiven', 'manualDiscount', 'discountReason', 'pay']); }

    /** Enter in the search box: an exact SKU (barcode scanner) adds the item straight away. */
    public function scan(): void
    {
        $v = ProductVariant::where('sku', trim($this->search))->first();
        if ($v) { $this->add($v->id); $this->search = ''; }
    }

    public function checkout(): void
    {
        $lines = $this->lines();
        if ($lines->isEmpty()) { Notification::make()->title('Bill đang trống')->warning()->send(); return; }
        $phone = preg_replace('/[\s.]/', '', $this->phone);
        if ($phone !== '' && ! preg_match('/^0\d{9}$/', $phone)) { Notification::make()->title('Số điện thoại gồm 10 chữ số, bắt đầu bằng 0')->danger()->send(); return; }
        if (! in_array($this->pay, ['cash', 'bank', 'card'], true)) return;
        $t = $this->totals($lines);
        if ($t['manual'] > 0 && trim($this->discountReason) === '') { Notification::make()->title('Nhập lý do giảm giá tay')->danger()->send(); return; }
        if ($this->pay === 'cash' && $this->cashGiven !== null && $this->cashGiven !== '' && $t['cash'] < $t['total']) { Notification::make()->title('Tiền khách đưa chưa đủ')->danger()->send(); return; }
        $amb = $this->ambassador();
        $ref = strtoupper(trim($this->refCode)) ?: null;

        try {
            $order = DB::transaction(function () use ($t, $phone, $amb, $ref) {
                $vs = ProductVariant::with('product')->whereIn('id', array_keys($this->cart))->lockForUpdate()->get()->keyBy('id');
                foreach ($this->cart as $id => $q) {
                    $v = $vs->get($id);
                    if (! $v || ! $v->is_available) throw new \RuntimeException('Có món vừa ngừng bán, vui lòng xem lại bill.');
                    if ($v->stock_qty !== null && $q > $v->stock_qty) throw new \RuntimeException("{$v->product->name} · {$v->name} chỉ còn {$v->stock_qty} trong kho.");
                }
                $customer = null;
                if ($phone !== '') {
                    $customer = Customer::firstOrNew(['phone' => $phone]);
                    if (! $customer->exists || $this->customerName !== '') $customer->name = $this->customerName ?: 'Khách showroom';
                    if (! $customer->first_ref_code && $ref) $customer->first_ref_code = $ref;
                    $customer->save();
                }
                do { $code = 'YS' . now()->format('ymd') . '-' . strtoupper(Str::random(4)); } while (Order::where('code', $code)->exists());
                $note = $t['manual'] ? 'Giảm tay ' . number_format($t['manual'], 0, ',', '.') . 'đ: ' . trim($this->discountReason) : null;
                $order = Order::create(['code' => $code, 'customer_id' => $customer?->id, 'channel' => 'showroom', 'ref_code' => $ref, 'ambassador_id' => $amb?->id,
                    'created_by' => auth()->id(), 'subtotal' => $t['sub'], 'discount' => $t['refDisc'] + $t['manual'], 'shipping_fee' => 0, 'total' => $t['total'],
                    'status' => 'completed', 'payment_method' => $this->pay, 'payment_status' => 'paid',
                    'ship_name' => $customer?->name ?? 'Khách mua tại quầy', 'ship_phone' => $phone ?: null, 'ship_address' => '01 Nguyễn Thiện Thuật (mua tại quầy)',
                    'internal_note' => $note]);
                foreach ($this->cart as $id => $q) {
                    $v = $vs->get($id);
                    $order->items()->create(['variant_id' => $v->id, 'product_name' => $v->product->name, 'variant_name' => $v->name, 'sku' => $v->sku,
                        'unit_price' => (int) $v->list_price, 'qty' => $q, 'line_total' => (int) $v->list_price * $q]);
                    if ($v->stock_qty !== null) StockMovement::create(['variant_id' => $v->id, 'type' => 'sale', 'qty' => $q, 'order_id' => $order->id, 'note' => 'Bán tại quầy']);
                }
                $policy = $amb?->policy();
                if ($amb && $policy && $policy->rate_percent !== null) {
                    Commission::create(['order_id' => $order->id, 'ambassador_id' => $amb->id, 'level' => 'direct', 'base_amount' => $t['total'],
                        'rate_percent' => $policy->rate_percent, 'amount' => (int) round($t['total'] * (float) $policy->rate_percent / 100), 'status' => 'pending']);
                }
                return $order;
            });
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            return;
        }
        $this->clearCart();
        $this->lastOrderId = $order->id;
        Notification::make()->title("Đã thu tiền đơn {$order->code}")->success()->send();
        $this->dispatch('pos-print', url: route('pos.bill', $order));
    }
}
