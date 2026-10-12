<?php
namespace Tests\Feature;

use App\Filament\Pages\Pos;
use App\Models\Ambassador;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Dry run of the POS: everything happens inside a transaction that is rolled back at the end. */
class PosDryRunTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pos_sale_dry_run(): void
    {
        $u = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($u);
        $a = ProductVariant::where('sku', 'TP-TCG-H0050-YS-01')->firstOrFail();
        $b = ProductVariant::where('sku', 'TP-ALG-H0001-NS-02')->firstOrFail();
        $c = ProductVariant::where('sku', 'SCK-VOH')->firstOrFail();
        Ambassador::create(['name' => 'HDV Thử', 'phone' => '0900000001', 'type' => 'guide', 'ref_code' => 'HDVTHU01', 'status' => 'active']);

        $lw = Livewire::test(Pos::class)->call('add', $a->id)
            ->call('add', $b->id)->call('add', $b->id)->call('add', $b->id)->call('add', $b->id)
            ->call('add', $c->id)->call('add', $c->id)
            ->set('refCode', 'HDVTHU01')->set('pay', 'cash')->set('cashGiven', '4000000')
            ->call('checkout');
        $o = Order::with(['items', 'commissions', 'statusLogs'])->latest('id')->firstOrFail();

        $out = "ĐƠN {$o->code} | kênh={$o->channel} | trạng thái={$o->status} | thanh toán={$o->payment_method}/{$o->payment_status} | NV={$o->created_by}\n";
        foreach ($o->items as $i) $out .= "  {$i->product_name} · {$i->variant_name} | {$i->sku} | {$i->unit_price} x {$i->qty} = {$i->line_total}\n";
        $out .= "  tạm tính={$o->subtotal} giảm={$o->discount} tổng={$o->total} | mã={$o->ref_code}\n";
        foreach ($o->commissions as $c2) $out .= "  hoa hồng: {$c2->rate_percent}% = {$c2->amount} ({$c2->status})\n";
        $out .= "  lịch sử: " . $o->statusLogs->map(fn ($l) => ($l->from_status ?? '-') . '→' . $l->to_status)->implode(', ') . "\n";
        $out .= "  bill url: " . route('pos.bill', $o) . "\n";
        fwrite(STDERR, $out);

        $this->assertSame('showroom', $o->channel);
        $this->assertSame(3880000, (int) $o->total);
        $this->assertSame(776000, (int) $o->commissions->first()->amount);
        $this->get(route('pos.bill', $o))->assertOk()->assertSee($o->code);
    }
}
