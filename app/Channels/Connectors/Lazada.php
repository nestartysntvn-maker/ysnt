<?php
namespace App\Channels\Connectors;

use App\Models\ChannelListing;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Lazada Open Platform, Vietnam endpoint. */
class Lazada extends Marketplace
{
    public const API = 'https://api.lazada.vn/rest';
    public const AUTH = 'https://auth.lazada.com/rest';

    /** uppercase HMAC-SHA256(app secret, path + sorted k.v) */
    public static function sign(string $path, array $params, string $secret): string
    {
        unset($params['sign']);
        ksort($params);
        $s = $path;
        foreach ($params as $k => $v) $s .= $k . $v;
        return strtoupper(hash_hmac('sha256', $s, $secret));
    }

    private function call(SalesChannel $c, string $method, string $path, array $params = [], bool $auth = false): array
    {
        $p = $params + ['app_key' => $this->cred($c, 'app_key'), 'timestamp' => (string) (int) round(microtime(true) * 1000), 'sign_method' => 'sha256'];
        if (! $auth) $p['access_token'] = $this->cred($c, 'access_token');
        $p['sign'] = self::sign($path, $p, $this->cred($c, 'app_secret'));
        $url = ($auth ? self::AUTH : self::API) . $path;
        $res = $method === 'GET' ? Http::timeout(30)->get($url, $p) : Http::timeout(30)->asForm()->post($url, $p);
        $j = $res->json() ?? [];
        if ((string) ($j['code'] ?? '-1') !== '0') throw new RuntimeException('Lazada: ' . ($j['message'] ?? mb_substr($res->body(), 0, 300)) . ' (' . ($j['code'] ?? $res->status()) . ')');
        return $j;
    }

    public function authorizeUrl(SalesChannel $c, string $redirect, string $state): string
    {
        return 'https://auth.lazada.com/oauth/authorize?' . http_build_query(['response_type' => 'code', 'force_auth' => 'true', 'redirect_uri' => $redirect, 'client_id' => $this->cred($c, 'app_key'), 'state' => $state]);
    }

    private function saveToken(SalesChannel $c, array $j): void
    {
        if (empty($j['access_token'])) throw new RuntimeException('Lazada không cấp token');
        $seller = collect($j['country_user_info'] ?? [])->firstWhere('country', 'vn') ?? collect($j['country_user_info'] ?? [])->first();
        if ($seller && ! $c->shop_id) $c->shop_id = (string) ($seller['seller_id'] ?? '');
        $this->store($c, ['access_token' => $j['access_token'], 'refresh_token' => $j['refresh_token'] ?? null,
            'access_expires_at' => time() + (int) ($j['expires_in'] ?? 0), 'refresh_expires_at' => time() + (int) ($j['refresh_expires_in'] ?? 0)]);
    }

    public function exchange(SalesChannel $c, array $query): void
    {
        if (empty($query['code'])) throw new RuntimeException('Lazada không trả mã cấp quyền');
        $this->saveToken($c, $this->call($c, 'POST', '/auth/token/create', ['code' => $query['code']], true));
    }

    public function refresh(SalesChannel $c): void { $this->saveToken($c, $this->call($c, 'POST', '/auth/token/refresh', ['refresh_token' => $this->cred($c, 'refresh_token')], true)); }

    public function ping(SalesChannel $c): string { return 'Kết nối được gian hàng ' . ($this->call($c, 'GET', '/seller/get')['data']['name'] ?? '?'); }

    public function fetchOrders(SalesChannel $c, Carbon $since): array
    {
        $orders = []; $offset = 0;
        do {
            $d = $this->call($c, 'GET', '/orders/get', ['update_after' => $since->toIso8601String(), 'sort_by' => 'updated_at', 'sort_direction' => 'ASC', 'limit' => 100, 'offset' => $offset])['data'] ?? [];
            $page = $d['orders'] ?? [];
            array_push($orders, ...$page);
            $offset += 100;
        } while (count($page) === 100 && $offset < 4000);
        $items = [];
        foreach (array_chunk(array_column($orders, 'order_id'), 50) as $ids) {
            foreach ($this->call($c, 'GET', '/orders/items/get', ['order_ids' => '[' . implode(',', $ids) . ']'])['data'] ?? [] as $row) $items[(string) $row['order_id']] = $row['order_items'] ?? [];
        }
        return array_map(fn ($o) => self::normalize($o, $items[(string) $o['order_id']] ?? []), $orders);
    }

    public static function normalize(array $o, array $items): array
    {
        $a = $o['address_shipping'] ?? [];
        $addr = implode(', ', array_filter([$a['address1'] ?? null, $a['address2'] ?? null, $a['address3'] ?? null, $a['address4'] ?? null, $a['address5'] ?? null, $a['city'] ?? null]));
        return ['id' => (string) $o['order_id'], 'created_at' => Carbon::parse($o['created_at'] ?? 'now')->toIso8601String(), 'status' => (string) (($o['statuses'] ?? [])[0] ?? ''),
            'buyer_id' => null, 'buyer_name' => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: ($o['customer_first_name'] ?? null), 'buyer_phone' => $a['phone'] ?? null,
            'address' => $addr ?: null, 'note' => $o['remarks'] ?? null, 'discount' => 0, 'shipping_fee' => (int) round((float) ($o['shipping_fee'] ?? 0)),
            'items' => self::group(array_map(fn ($it) => ['sku_id' => isset($it['sku_id']) ? (string) $it['sku_id'] : null, 'sku' => $it['sku'] ?? null,
                'name' => (string) ($it['name'] ?? ''), 'variant' => $it['variation'] ?? null, 'qty' => 1,
                'unit_price' => (int) round((float) ($it['item_price'] ?? 0) - (float) ($it['voucher_seller'] ?? 0))], $items))];
    }

    public function pushStock(SalesChannel $c, ChannelListing $l, int $qty): void
    {
        if (! $l->platform_item_id || ! $l->platform_sku_id) throw new RuntimeException('Dòng ghép SKU thiếu item id hoặc sku id Lazada');
        $x = fn ($v) => htmlspecialchars((string) $v, ENT_XML1);
        $xml = '<Request><Product><Skus><Sku><ItemId>' . $x($l->platform_item_id) . '</ItemId><SkuId>' . $x($l->platform_sku_id) . '</SkuId>'
            . ($l->platform_sku ? '<SellerSku>' . $x($l->platform_sku) . '</SellerSku>' : '') . '<SellableQuantity>' . $qty . '</SellableQuantity></Sku></Skus></Product></Request>';
        $this->call($c, 'POST', '/product/stock/sellable/update', ['payload' => $xml]);
    }
}
