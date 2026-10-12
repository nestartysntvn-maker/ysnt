<?php
namespace App\Channels\Connectors;

use App\Models\ChannelListing;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Shopee Open Platform v2. app_key = Partner ID, app_secret = Partner Key; shop_id comes back from the auth redirect. */
class Shopee extends Marketplace
{
    private function host(SalesChannel $c): string { return ! empty(($c->credentials ?? [])['sandbox']) ? 'https://partner.test-stable.shopeemobile.com' : 'https://partner.shopeemobile.com'; }

    /** HMAC-SHA256(partner key, partner_id + path + timestamp [+ access_token + shop_id]) */
    public static function sign(string $partnerId, string $path, int $ts, string $key, string $token = '', string $shopId = ''): string
    {
        return hash_hmac('sha256', $partnerId . $path . $ts . $token . $shopId, $key);
    }

    private function publicUrl(SalesChannel $c, string $path): string
    {
        $ts = time(); $pid = $this->cred($c, 'app_key');
        return $this->host($c) . $path . '?' . http_build_query(['partner_id' => (int) $pid, 'timestamp' => $ts, 'sign' => self::sign($pid, $path, $ts, $this->cred($c, 'app_secret'))]);
    }

    private function call(SalesChannel $c, string $method, string $path, array $params = []): array
    {
        $ts = time(); $pid = $this->cred($c, 'app_key'); $token = $this->cred($c, 'access_token'); $shop = (string) $c->shop_id;
        if ($shop === '') throw new \App\Channels\NotConfigured('Gian hàng Shopee chưa có shop_id. Bấm Cấp quyền gian hàng.');
        $q = ['partner_id' => (int) $pid, 'timestamp' => $ts, 'access_token' => $token, 'shop_id' => (int) $shop, 'sign' => self::sign($pid, $path, $ts, $this->cred($c, 'app_secret'), $token, $shop)];
        $url = $this->host($c) . $path . '?' . http_build_query($method === 'GET' ? $q + $params : $q);
        $res = $method === 'GET' ? Http::timeout(30)->get($url) : Http::timeout(30)->asJson()->post($url, $params);
        $j = $res->json() ?? [];
        if (! empty($j['error']) || ! $res->ok()) throw new RuntimeException('Shopee: ' . ($j['message'] ?? mb_substr($res->body(), 0, 300)) . ' (' . ($j['error'] ?? $res->status()) . ')');
        return $j['response'] ?? $j;
    }

    /** Shopee appends ?code=&shop_id= to the redirect, so the channel is remembered in the session, not in the URL */
    public function authorizeUrl(SalesChannel $c, string $redirect, string $state): string
    {
        return $this->publicUrl($c, '/api/v2/shop/auth_partner') . '&redirect=' . rawurlencode($redirect);
    }

    private function saveToken(SalesChannel $c, array $j): void
    {
        if (empty($j['access_token'])) throw new RuntimeException('Shopee không cấp token: ' . ($j['message'] ?? $j['error'] ?? 'không rõ'));
        $this->store($c, ['access_token' => $j['access_token'], 'refresh_token' => $j['refresh_token'] ?? null, 'access_expires_at' => time() + (int) ($j['expire_in'] ?? 14400)]);
    }

    public function exchange(SalesChannel $c, array $query): void
    {
        if (empty($query['code']) || empty($query['shop_id'])) throw new RuntimeException('Shopee không trả mã cấp quyền hoặc shop_id');
        $c->shop_id = (string) $query['shop_id'];
        $res = Http::timeout(30)->asJson()->post($this->publicUrl($c, '/api/v2/auth/token/get'), ['code' => $query['code'], 'shop_id' => (int) $query['shop_id'], 'partner_id' => (int) $this->cred($c, 'app_key')]);
        $this->saveToken($c, $res->json() ?? []);
    }

    public function refresh(SalesChannel $c): void
    {
        $res = Http::timeout(30)->asJson()->post($this->publicUrl($c, '/api/v2/auth/access_token/get'), ['refresh_token' => $this->cred($c, 'refresh_token'), 'shop_id' => (int) $c->shop_id, 'partner_id' => (int) $this->cred($c, 'app_key')]);
        $this->saveToken($c, $res->json() ?? []);
    }

    public function ping(SalesChannel $c): string { return 'Kết nối được gian hàng ' . ($this->call($c, 'GET', '/api/v2/shop/get_shop_info')['shop_name'] ?? '?'); }

    public function fetchOrders(SalesChannel $c, Carbon $since): array
    {
        $from = max($since->timestamp, now()->subDays(15)->timestamp + 60); // API allows a 15-day window
        $sns = []; $cursor = ''; $guard = 0;
        do {
            $d = $this->call($c, 'GET', '/api/v2/order/get_order_list', ['time_range_field' => 'update_time', 'time_from' => $from, 'time_to' => time(), 'page_size' => 100, 'cursor' => $cursor]);
            foreach ($d['order_list'] ?? [] as $o) $sns[] = $o['order_sn'];
            $cursor = ! empty($d['more']) ? (string) ($d['next_cursor'] ?? '') : '';
        } while ($cursor !== '' && ++$guard < 40);
        $rows = [];
        foreach (array_chunk(array_unique($sns), 50) as $chunk) {
            $d = $this->call($c, 'GET', '/api/v2/order/get_order_detail', ['order_sn_list' => implode(',', $chunk),
                'response_optional_fields' => 'buyer_user_id,buyer_username,estimated_shipping_fee,recipient_address,item_list,note,total_amount']);
            foreach ($d['order_list'] ?? [] as $o) $rows[] = self::normalize($o);
        }
        return $rows;
    }

    public static function normalize(array $o): array
    {
        $a = $o['recipient_address'] ?? [];
        return ['id' => (string) $o['order_sn'], 'created_at' => Carbon::createFromTimestamp((int) ($o['create_time'] ?? time()))->toIso8601String(), 'status' => (string) ($o['order_status'] ?? ''),
            'buyer_id' => isset($o['buyer_user_id']) ? (string) $o['buyer_user_id'] : null, 'buyer_name' => $a['name'] ?? ($o['buyer_username'] ?? null), 'buyer_phone' => $a['phone'] ?? null,
            'address' => $a['full_address'] ?? null, 'note' => $o['message_to_seller'] ?? ($o['note'] ?? null), 'discount' => 0,
            'shipping_fee' => (int) round((float) ($o['estimated_shipping_fee'] ?? 0)),
            'items' => array_map(fn ($it) => ['sku_id' => (string) (! empty($it['model_id']) ? $it['model_id'] : $it['item_id']), 'sku' => ($it['model_sku'] ?? '') ?: ($it['item_sku'] ?? null),
                'name' => (string) ($it['item_name'] ?? ''), 'variant' => $it['model_name'] ?? null, 'qty' => (int) ($it['model_quantity_purchased'] ?? 1),
                'unit_price' => (int) round((float) ($it['model_discounted_price'] ?? $it['model_original_price'] ?? 0))], $o['item_list'] ?? [])];
    }

    /** platform_item_id = item_id, platform_sku_id = model_id (or the item_id itself when the item has no variations) */
    public function pushStock(SalesChannel $c, ChannelListing $l, int $qty): void
    {
        if (! $l->platform_item_id) throw new RuntimeException('Dòng ghép SKU thiếu item id Shopee');
        $model = $l->platform_sku_id && $l->platform_sku_id !== $l->platform_item_id ? (int) $l->platform_sku_id : 0;
        $this->call($c, 'POST', '/api/v2/product/update_stock', ['item_id' => (int) $l->platform_item_id, 'stock_list' => [['model_id' => $model, 'seller_stock' => [['stock' => $qty]]]]]);
    }
}
