<?php
namespace App\Channels\Connectors;

use App\Inbox\Inbox;
use App\Models\ChannelListing;
use App\Models\Conversation;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** TikTok Shop Partner API (versioned 202309 endpoints). Needs an app approved on partner.tiktokshop.com. */
class TikTokShop extends Marketplace
{
    public const API = 'https://open-api.tiktokglobalshop.com';
    public const AUTH = 'https://auth.tiktok-shops.com';

    /** HMAC-SHA256(secret, secret + path + sorted k.v (no sign/access_token) + body + secret) */
    public static function sign(string $path, array $query, string $body, string $secret): string
    {
        unset($query['sign'], $query['access_token']);
        ksort($query);
        $s = $path;
        foreach ($query as $k => $v) $s .= $k . $v;
        return hash_hmac('sha256', $secret . $s . $body . $secret, $secret);
    }

    private function call(SalesChannel $c, string $method, string $path, array $query = [], ?array $body = null, bool $shop = true): array
    {
        $query += ['app_key' => $this->cred($c, 'app_key'), 'timestamp' => time()];
        if ($shop) $query['shop_cipher'] = $this->cred($c, 'shop_cipher');
        $raw = $method === 'GET' ? '' : json_encode($body ? $body : new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $query['sign'] = self::sign($path, $query, $raw, $this->cred($c, 'app_secret'));
        $req = Http::timeout(30)->withHeaders(['x-tts-access-token' => $this->cred($c, 'access_token')]);
        $url = self::API . $path . '?' . http_build_query($query);
        $res = $method === 'GET' ? $req->get($url) : $req->withBody($raw, 'application/json')->send($method, $url);
        $j = $res->json() ?? [];
        if ((int) ($j['code'] ?? -1) !== 0) throw new RuntimeException('TikTok Shop: ' . ($j['message'] ?? mb_substr($res->body(), 0, 300)) . ' (mã ' . ($j['code'] ?? $res->status()) . ')');
        return $j['data'] ?? [];
    }

    public function authorizeUrl(SalesChannel $c, string $redirect, string $state): string
    {
        return 'https://services.tiktokshop.com/open/authorize?' . http_build_query(['service_id' => $this->cred($c, 'service_id'), 'state' => $state]);
    }

    private function token(SalesChannel $c, array $q): void
    {
        $res = Http::timeout(30)->get(self::AUTH . '/api/v2/token/' . (isset($q['auth_code']) ? 'get' : 'refresh'), $q + ['app_key' => $this->cred($c, 'app_key'), 'app_secret' => $this->cred($c, 'app_secret')]);
        $j = $res->json() ?? [];
        if ((int) ($j['code'] ?? -1) !== 0 || empty($j['data']['access_token'])) throw new RuntimeException('TikTok Shop không cấp token: ' . ($j['message'] ?? mb_substr($res->body(), 0, 300)));
        $d = $j['data'];
        $this->store($c, ['access_token' => $d['access_token'], 'refresh_token' => $d['refresh_token'] ?? null,
            'access_expires_at' => $d['access_token_expire_in'] ?? null, 'refresh_expires_at' => $d['refresh_token_expire_in'] ?? null, 'seller_name' => $d['seller_name'] ?? null]);
    }

    public function exchange(SalesChannel $c, array $query): void
    {
        if (empty($query['code'])) throw new RuntimeException('TikTok Shop không trả mã cấp quyền');
        $this->token($c, ['auth_code' => $query['code'], 'grant_type' => 'authorized_code']);
        $shop = collect($this->call($c, 'GET', '/authorization/202309/shops', [], null, false)['shops'] ?? [])->first();
        if (! $shop) throw new RuntimeException('Tài khoản chưa có gian hàng nào được cấp quyền');
        $c->shop_id = (string) $shop['id'];
        $this->store($c, ['shop_cipher' => $shop['cipher'] ?? null, 'region' => $shop['region'] ?? null]);
        $wh = collect($this->call($c, 'GET', '/logistics/202309/warehouses')['warehouses'] ?? [])->sortByDesc(fn ($w) => (int) ! empty($w['is_default']))->first();
        if ($wh) $this->store($c, ['warehouse_id' => (string) $wh['id']]);
    }

    public function refresh(SalesChannel $c): void { $this->token($c, ['refresh_token' => $this->cred($c, 'refresh_token'), 'grant_type' => 'refresh_token']); }

    public function ping(SalesChannel $c): string
    {
        $shop = collect($this->call($c, 'GET', '/authorization/202309/shops', [], null, false)['shops'] ?? [])->first();
        return 'Kết nối được gian hàng ' . ($shop['name'] ?? '?') . ' (' . ($shop['region'] ?? '') . ')';
    }

    public function fetchOrders(SalesChannel $c, Carbon $since): array
    {
        $rows = []; $token = null; $guard = 0;
        do {
            $q = ['page_size' => 50, 'sort_field' => 'update_time', 'sort_order' => 'ASC'] + ($token ? ['page_token' => $token] : []);
            $d = $this->call($c, 'POST', '/order/202309/orders/search', $q, ['update_time_ge' => $since->timestamp, 'update_time_lt' => time()]);
            foreach ($d['orders'] ?? [] as $o) $rows[] = self::normalize($o);
            $token = $d['next_page_token'] ?? null;
        } while ($token && ++$guard < 40);
        return $rows;
    }

    /** sale_price is what the seller earns per unit after the seller's own discount; platform-funded discounts are not ours */
    public static function normalize(array $o): array
    {
        $a = $o['recipient_address'] ?? [];
        return ['id' => (string) $o['id'], 'created_at' => Carbon::createFromTimestamp((int) ($o['create_time'] ?? time()))->toIso8601String(), 'status' => (string) ($o['status'] ?? ''),
            'buyer_id' => isset($o['user_id']) ? (string) $o['user_id'] : null, 'buyer_name' => $a['name'] ?? null, 'buyer_phone' => $a['phone_number'] ?? null,
            'address' => $a['full_address'] ?? null, 'note' => $o['buyer_message'] ?? null, 'discount' => 0,
            'shipping_fee' => (int) round((float) ($o['payment']['shipping_fee'] ?? 0)),
            'items' => self::group(array_map(fn ($li) => ['sku_id' => isset($li['sku_id']) ? (string) $li['sku_id'] : null, 'sku' => $li['seller_sku'] ?? null,
                'name' => (string) ($li['product_name'] ?? ''), 'variant' => $li['sku_name'] ?? null, 'qty' => (int) ($li['quantity'] ?? 1),
                'unit_price' => (int) round((float) ($li['sale_price'] ?? $li['original_price'] ?? 0))], $o['line_items'] ?? []))];
    }

    public function pushStock(SalesChannel $c, ChannelListing $l, int $qty): void
    {
        if (! $l->platform_item_id || ! $l->platform_sku_id) throw new RuntimeException('Dòng ghép SKU thiếu product id hoặc sku id TikTok');
        $this->call($c, 'POST', '/product/202309/products/' . $l->platform_item_id . '/inventory/update', [],
            ['skus' => [['id' => (string) $l->platform_sku_id, 'inventory' => [['warehouse_id' => $this->cred($c, 'warehouse_id'), 'quantity' => $qty]]]]]);
    }

    /** buyer chats -> one inbox; external_ref keeps "channel id:TikTok conversation id" so replies find their shop */
    public function pollChats(SalesChannel $c): int
    {
        $n = 0;
        foreach ($this->call($c, 'GET', '/customer_service/202309/conversations', ['page_size' => 20])['conversations'] ?? [] as $cv) {
            if ((int) ($cv['unread_count'] ?? 0) === 0) continue;
            $buyer = collect($cv['participants'] ?? [])->firstWhere('role', 'BUYER') ?? [];
            $msgs = $this->call($c, 'GET', '/customer_service/202309/conversations/' . $cv['id'] . '/messages', ['page_size' => 10, 'sort_order' => 'DESC'])['messages'] ?? [];
            foreach (array_reverse($msgs) as $m) {
                if (($m['sender']['role'] ?? '') !== 'BUYER') continue;
                $content = json_decode((string) ($m['content'] ?? ''), true);
                $text = ($m['type'] ?? '') === 'TEXT' ? (string) ($content['content'] ?? '') : '[' . ($m['type'] ?? 'tin') . ']';
                if (Inbox::receive('tiktok', (string) ($buyer['user_id'] ?? $buyer['im_user_id'] ?? $cv['id']), $text,
                    ['name' => $buyer['nickname'] ?? null, 'message_id' => (string) $m['id'], 'external_ref' => $c->id . ':' . $cv['id']])) $n++;
            }
        }
        return $n;
    }

    public static function sendChat(Conversation $conv, string $text): array
    {
        [$channelId, $cvId] = array_pad(explode(':', (string) $conv->external_ref, 2), 2, null);
        $c = SalesChannel::find((int) $channelId);
        if (! $c || ! $cvId) return ['failed', null, 'Không tìm thấy gian hàng TikTok của hội thoại'];
        if ($c->status !== 'active' || empty(($c->credentials ?? [])['sync_chat'])) return ['dry_run', null, 'Chạy thử: gian hàng chưa bật chat'];
        $d = (new self())->call($c, 'POST', '/customer_service/202309/conversations/' . $cvId . '/messages', [], ['type' => 'TEXT', 'content' => json_encode(['content' => mb_substr($text, 0, 2000)], JSON_UNESCAPED_UNICODE)]);
        return ['sent', isset($d['message_id']) ? (string) $d['message_id'] : null, null];
    }
}
