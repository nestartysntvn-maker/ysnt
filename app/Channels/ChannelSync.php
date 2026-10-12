<?php
namespace App\Channels;

use App\Models\ChannelListing;
use App\Models\ChannelSyncLog;
use App\Channels\Connectors\Marketplace;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelSync
{
    public const PLATFORMS = ['tiktok' => 'TikTok Shop', 'shopee' => 'Shopee', 'lazada' => 'Lazada'];
    /** Maps a platform status onto ours; anything unknown stays "new" for a person to look at. */
    public const STATUS_MAP = ['UNPAID' => 'new', 'AWAITING_SHIPMENT' => 'confirmed', 'READY_TO_SHIP' => 'confirmed', 'IN_TRANSIT' => 'shipping', 'SHIPPED' => 'shipping', 'DELIVERED' => 'completed', 'COMPLETED' => 'completed', 'CANCELLED' => 'cancelled', 'CANCELED' => 'cancelled', 'RETURNED' => 'returned',
        // TikTok
        'AWAITING_COLLECTION' => 'confirmed', 'PARTIALLY_SHIPPING' => 'shipping',
        // Shopee
        'PROCESSED' => 'confirmed', 'RETRY_SHIP' => 'confirmed', 'TO_CONFIRM_RECEIVE' => 'shipping', 'TO_RETURN' => 'returned',
        // Lazada (lower case on the wire)
        'PENDING' => 'new', 'TOPACK' => 'confirmed', 'TOSHIP' => 'confirmed', 'PACKED' => 'confirmed', 'READY_TO_SHIP_PENDING' => 'confirmed', 'CONFIRMED' => 'completed', 'FAILED_DELIVERY' => 'returned'];

    public static function connector(SalesChannel $c): Connector
    {
        $m = Marketplace::make($c->platform);
        return $m && Marketplace::ready($c) ? $m : new PendingConnector(self::PLATFORMS[$c->platform] ?? $c->platform);
    }

    /** tokens expire (Shopee every 4 hours); renew an hour ahead */
    public static function refreshToken(SalesChannel $c): void
    {
        $m = Marketplace::make($c->platform);
        if (! $m || ! Marketplace::hasApp($c) || ! $m->needsRefresh($c)) return;
        try {
            $m->refresh($c);
            ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'token', 'status' => 'ok']);
        } catch (\Throwable $e) {
            $c->update(['status' => 'error', 'last_error' => mb_substr('Gia hạn token lỗi: ' . $e->getMessage(), 0, 500)]);
            ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'token', 'status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 2000)]);
        }
    }

    public static function syncOrders(SalesChannel $c): void
    {
        $since = ($c->last_order_sync_at ?? now()->subDays(3))->copy()->subMinutes(15);
        try {
            $rows = self::connector($c)->fetchOrders($c, $since);
            [$created, $skipped] = self::importOrders($c, $rows);
            $c->update(['last_order_sync_at' => now(), 'status' => 'active', 'last_error' => null]);
            ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'orders', 'status' => 'ok', 'items' => $created, 'message' => $skipped ? implode("\n", array_slice($skipped, 0, 20)) : null]);
        } catch (\Throwable $e) {
            $c->update(['status' => $e instanceof NotConfigured ? $c->status : 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'orders', 'status' => $e instanceof NotConfigured ? 'skipped' : 'error', 'message' => mb_substr($e->getMessage(), 0, 2000)]);
        }
    }

    /** Idempotent: an order already imported (same shop + platform order id) only has its status refreshed. */
    public static function importOrders(SalesChannel $c, array $rows): array
    {
        $created = 0; $skipped = [];
        foreach ($rows as $r) {
            $status = self::STATUS_MAP[strtoupper((string) $r['status'])] ?? 'new';
            $existing = Order::where('sales_channel_id', $c->id)->where('channel_order_id', $r['id'])->first();
            if ($existing) { if ($existing->status !== $status) $existing->update(['status' => $status]); continue; }
            $lines = []; $missing = [];
            foreach ($r['items'] as $it) {
                $l = $it['sku_id'] ? ChannelListing::where('sales_channel_id', $c->id)->where('platform_sku_id', $it['sku_id'])->first() : null;
                $v = $l?->variant ?? ($it['sku'] ? ProductVariant::where('sku', $it['sku'])->first() : null);
                if (! $v) { $missing[] = ($it['sku'] ?: $it['sku_id']) . ' ' . $it['name']; }
                $lines[] = ['variant_id' => $v?->id, 'product_name' => $v?->product?->name ?? $it['name'], 'variant_name' => $v?->name ?? ($it['variant'] ?? ''),
                    'sku' => $v?->sku ?? $it['sku'], 'unit_price' => (int) $it['unit_price'], 'qty' => (int) $it['qty'], 'line_total' => (int) $it['unit_price'] * (int) $it['qty']];
            }
            if ($missing) $skipped[] = "Đơn {$r['id']}: chưa ghép SKU cho " . implode(', ', $missing) . ' (đơn vẫn được nhập, cần ghép để trừ kho đúng)';
            DB::transaction(function () use ($c, $r, $lines, $status, &$created) {
                $sub = array_sum(array_column($lines, 'line_total'));
                $cust = ! empty($r['buyer_phone']) && preg_match('/^0\d{9}$/', $r['buyer_phone']) ? Customer::firstOrCreate(['phone' => $r['buyer_phone']], ['name' => $r['buyer_name'] ?: 'Khách ' . $c->name]) : null;
                // marketplaces mask phones; the buyer id still ties repeat orders to one customer once staff link it
                if (! empty($r['buyer_id'])) { $ident = CustomerIdentity::resolve($c->platform, (string) $r['buyer_id'], $r['buyer_name'] ?? null, $r['buyer_phone'] ?? null); $cust ??= $ident->customer; }
                $o = Order::create(['code' => 'YS' . now()->format('ymd') . '-' . strtoupper(Str::random(4)), 'customer_id' => $cust?->id, 'channel' => $c->platform,
                    'sales_channel_id' => $c->id, 'channel_order_id' => $r['id'], 'subtotal' => $sub, 'discount' => (int) $r['discount'], 'shipping_fee' => (int) $r['shipping_fee'],
                    'total' => max(0, $sub - (int) $r['discount']), 'status' => $status, 'payment_method' => 'platform', 'payment_status' => 'paid',
                    'ship_name' => $r['buyer_name'], 'ship_phone' => $r['buyer_phone'], 'ship_address' => $r['address'], 'note' => $r['note'],
                    'created_at' => Carbon::parse($r['created_at'])]);
                $o->items()->createMany($lines);
                $created++;
            });
        }
        return [$created, $skipped];
    }

    public static function syncStock(SalesChannel $c): void
    {
        $n = 0;
        try {
            foreach ($c->listings()->where('sync_stock', true)->with('variant')->get() as $l) {
                if ($l->variant->stock_qty === null) continue; // kho chua theo doi thi khong day so 0 len san
                self::connector($c)->pushStock($c, $l, max(0, (int) $l->variant->stock_qty - (int) $l->stock_buffer));
                $l->update(['last_synced_at' => now()]); $n++;
            }
            $c->update(['last_stock_sync_at' => now()]);
            ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'stock', 'status' => 'ok', 'items' => $n]);
        } catch (\Throwable $e) {
            ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'stock', 'status' => $e instanceof NotConfigured ? 'skipped' : 'error', 'items' => $n, 'message' => mb_substr($e->getMessage(), 0, 2000)]);
        }
    }
}
