<?php
namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\ZaloMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Dry run: a test web order goes new -> shipping; everything rolled back afterwards. */
class ZaloDryRunTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zalo_dry_run(): void
    {
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $o = Order::create(['code' => 'YSTEST-ZALO', 'channel' => 'web', 'subtotal' => 2750000, 'discount' => 0, 'shipping_fee' => 0, 'total' => 2750000,
            'status' => 'new', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'ship_name' => 'Khách Thử', 'ship_phone' => '0905123456', 'ship_address' => 'Nha Trang']);
        $o->items()->create(['product_name' => 'Tổ Yến Tinh Chế Yến Sào Nha Trang', 'variant_name' => 'Hộp 50g', 'sku' => 'TP-TCG-H0050-YS-01', 'unit_price' => 2750000, 'qty' => 1, 'line_total' => 2750000]);
        \App\Zalo\Zns::forOrder('order_confirmed', $o->fresh());          // afterCommit never fires inside a test transaction, so call directly
        $o->update(['status' => 'shipping']); \App\Zalo\Zns::forOrder('order_shipping', $o->fresh());
        \App\Zalo\Zns::forOrder('order_shipping', $o->fresh());           // second call must not duplicate
        $pos = Order::create(['code' => 'YSTEST-POS', 'channel' => 'showroom', 'subtotal' => 1, 'discount' => 0, 'shipping_fee' => 0, 'total' => 1, 'status' => 'completed', 'payment_status' => 'paid', 'ship_phone' => '0905123456']);
        $none = \App\Zalo\Zns::forOrder('order_confirmed', $pos);
        $out = '';
        foreach (ZaloMessage::with('template')->where('order_id', $o->id)->orderBy('id')->get() as $m)
            $out .= "  {$m->template->name} | gửi tới {$m->phone} | {$m->status} | " . json_encode($m->payload['template_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " | {$m->error}\n";
        $out .= "  đơn showroom (POS): " . ($none ? 'CÓ ghi tin (sai)' : 'không ghi tin (đúng)') . "\n";
        fwrite(STDERR, $out);
        $this->assertSame(2, ZaloMessage::where('order_id', $o->id)->count());
        $this->assertNull($none);
    }
}
