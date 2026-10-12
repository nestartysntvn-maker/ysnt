<?php
namespace App\Http\Controllers;

use App\Models\Ambassador;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $in = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'address' => 'required|string|min:6|max:500',
            'note' => 'nullable|string|max:500',
            'pay' => 'required|in:cod,bank,vnpay',
            'ref' => 'nullable|string|max:32',
            'items' => 'required|array|min:1|max:50',
            'items.*.id' => 'required|integer',
            'items.*.q' => 'required|integer|min:1|max:99',
        ], [
            'phone.regex' => 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.',
        ]);

        $qty = collect($in['items'])->groupBy('id')->map(fn ($g) => min(99, $g->sum('q')));
        $variants = ProductVariant::with('product')->whereIn('id', $qty->keys())->get()->keyBy('id');
        foreach ($qty as $id => $q) {
            $v = $variants->get($id);
            if (! $v || ! $v->is_available || $v->product->status !== 'active') {
                return response()->json(['message' => 'Một sản phẩm trong giỏ vừa hết hàng hoặc không còn bán. Vui lòng xem lại giỏ.'], 422);
            }
        }

        $ref = ShopController::cleanRef($in['ref'] ?? null);
        $amb = $ref ? Ambassador::where('ref_code', $ref)->where('status', 'active')->first() : null;
        $policy = $amb?->policy();

        $order = DB::transaction(function () use ($in, $qty, $variants, $ref, $amb, $policy) {
            $subtotal = 0;
            $lines = [];
            foreach ($qty as $id => $q) {
                $v = $variants->get($id);
                $line = (int) $v->list_price * $q;
                $subtotal += $line;
                $lines[] = ['variant_id' => $v->id, 'product_name' => $v->product->name, 'variant_name' => $v->name,
                    'sku' => $v->sku, 'unit_price' => (int) $v->list_price, 'qty' => $q, 'line_total' => $line];
            }
            $discPct = $policy?->customer_discount_percent !== null ? (float) $policy->customer_discount_percent : 0;
            $discount = (int) round($subtotal * $discPct / 100);
            $total = $subtotal - $discount;

            $customer = Customer::firstOrNew(['phone' => $in['phone']]);
            $customer->name = $in['name'];
            $customer->address = $in['address'];
            if (! $customer->first_ref_code && $ref) {
                $customer->first_ref_code = $ref;
            }
            $customer->save();

            do {
                $code = 'YS' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
            } while (Order::where('code', $code)->exists());

            $order = Order::create([
                'code' => $code, 'customer_id' => $customer->id, 'channel' => 'web',
                'ref_code' => $ref, 'ambassador_id' => $amb?->id,
                'subtotal' => $subtotal, 'discount' => $discount, 'shipping_fee' => 0, 'total' => $total,
                'status' => 'new', 'payment_method' => $in['pay'], 'payment_status' => 'unpaid',
                'ship_name' => $in['name'], 'ship_phone' => $in['phone'], 'ship_address' => $in['address'],
                'note' => $in['note'] ?? null,
            ]);
            $order->items()->createMany($lines);

            if ($amb && $policy && $policy->rate_percent !== null) {
                Commission::create([
                    'order_id' => $order->id, 'ambassador_id' => $amb->id, 'level' => 'direct',
                    'base_amount' => $total, 'rate_percent' => $policy->rate_percent,
                    'amount' => (int) round($total * (float) $policy->rate_percent / 100), 'status' => 'pending',
                ]);
            }
            return $order;
        });

        return response()->json([
            'code' => $order->code, 'total' => (int) $order->total, 'discount' => (int) $order->discount,
            'pay' => $order->payment_method, 'ref' => $order->ref_code, 'ref_valid' => (bool) $amb,
        ]);
    }
}
