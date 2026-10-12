<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class AdminPagesTest extends TestCase
{
    public function test_admin_pages_render(): void
    {
        $u = User::where('role', 'admin')->firstOrFail();
        $p = \App\Models\Product::first();
        $urls = ['/admin', '/admin/orders', '/admin/customers', '/admin/customers/nhap', '/admin/customers/create', '/admin/khoa-xuat-file', '/admin/products', '/admin/products/create', "/admin/products/{$p->id}/edit",
                 '/admin/categories', '/admin/stock-movements', '/admin/stock-movements/create', '/admin/ambassadors', '/admin/ambassadors/create',
                 '/admin/commission-policies', '/admin/commission-policies/1/edit', '/admin/commissions', '/admin/payout-requests', '/admin/payout-requests/create',
                 '/admin/users', '/admin/users/create', "/admin/users/{$u->id}/edit", '/admin/activity-logs', '/admin/sales-channels', '/admin/sales-channels/create', '/admin/channel-listings', '/admin/channel-listings/create', '/admin/profile', '/admin/pos', '/admin/sales-targets', '/admin/tour-visits', '/admin/tour-visits/create', '/admin/zalo', '/admin/zalo-templates', '/admin/zalo-templates/' . \App\Models\ZaloTemplate::min('id') . '/edit', '/admin/zalo-messages'];
        $bad = [];
        foreach ($urls as $url) { $r = $this->actingAs($u)->get($url); if ($r->status() !== 200) $bad[] = $url . ' => ' . $r->status() . ' ' . mb_substr(strip_tags((string) ($r->exception?->getMessage() ?? '')), 0, 300); }
        fwrite(STDERR, "checked " . count($urls) . " pages\n" . implode("\n", $bad) . "\n");
        $this->assertSame([], $bad);
    }
}
