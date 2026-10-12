<?php
namespace App\Meta;

use App\Models\AppSetting;
use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

/** Facebook Page / Instagram messaging + Pixel Conversions API, all through the Graph API with the page token. */
class Meta
{
    public static function version(): string { return AppSetting::get('meta_graph_version') ?: 'v23.0'; }
    public static function graph(string $path): string { return 'https://graph.facebook.com/' . self::version() . '/' . ltrim($path, '/'); }
    public static function inboxLive(): bool { return AppSetting::enabled('meta_inbox_live') && AppSetting::get('meta_page_token') && AppSetting::get('meta_page_id'); }
    public static function capiReady(): bool { return AppSetting::get('meta_pixel_id') && AppSetting::get('meta_capi_token'); }

    /** X-Hub-Signature-256 over the raw body with the app secret */
    public static function signatureOk(string $raw, ?string $header): bool
    {
        $secret = (string) AppSetting::get('meta_app_secret');
        return $secret !== '' && $header && hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $header);
    }

    /** @return array{0:string,1:?string,2:?string} [status, message id, error] */
    public static function sendText(string $recipientId, string $text): array
    {
        if (! self::inboxLive()) return ['dry_run', null, 'Chạy thử: chưa bật gửi thật cho Facebook, Instagram'];
        $res = Http::timeout(15)->post(self::graph(AppSetting::get('meta_page_id') . '/messages') . '?access_token=' . urlencode((string) AppSetting::get('meta_page_token')), [
            'recipient' => ['id' => $recipientId], 'messaging_type' => 'RESPONSE', 'message' => ['text' => mb_substr($text, 0, 2000)]]);
        return $res->ok() ? ['sent', $res->json('message_id'), null] : ['failed', null, mb_substr((string) ($res->json('error.message') ?? $res->body()), 0, 500)];
    }

    private static function h(?string $v): ?string { $v = trim(mb_strtolower((string) $v)); return $v === '' ? null : hash('sha256', $v); }

    /** server-side Purchase; event_id = order code so the browser pixel event is de-duplicated */
    public static function purchase(Order $o, array $ctx): void
    {
        $o->loadMissing('items');
        $phone = \App\Zalo\Zns::phone84($o->ship_phone);
        $event = ['event_name' => 'Purchase', 'event_time' => $o->created_at->timestamp, 'event_id' => $o->code, 'action_source' => 'website',
            'event_source_url' => $ctx['url'] ?? url('/thanh-toan'),
            'user_data' => array_filter(['ph' => $phone ? [self::h($phone)] : null, 'fn' => $o->ship_name ? [self::h($o->ship_name)] : null,
                'external_id' => $o->customer_id ? [self::h((string) $o->customer_id)] : null, 'country' => [self::h('vn')],
                'client_ip_address' => $ctx['ip'] ?? null, 'client_user_agent' => $ctx['ua'] ?? null, 'fbp' => $ctx['fbp'] ?? null, 'fbc' => $ctx['fbc'] ?? null]),
            'custom_data' => ['currency' => 'VND', 'value' => (int) $o->total, 'content_type' => 'product', 'num_items' => (int) $o->items->sum('qty'),
                'content_ids' => $o->items->map(fn ($i) => (string) ($i->sku ?: $i->variant_id))->values()->all()]];
        $body = ['data' => [$event]] + (($t = AppSetting::get('meta_test_event_code')) ? ['test_event_code' => $t] : []);
        $shown = ['event' => 'Purchase', 'event_id' => $o->code, 'value' => (int) $o->total, 'user_data_keys' => array_keys($event['user_data'])];
        if (! self::capiReady() || ! AppSetting::enabled('meta_capi_live')) {
            IntegrationLog::write('meta_capi', 'purchase', 'dry_run', self::capiReady() ? 'Chạy thử: chưa bật gửi thật' : 'Chạy thử: chưa có Pixel ID, mã Conversions API', $shown, $o->code);
            return;
        }
        $res = Http::timeout(15)->post(self::graph(AppSetting::get('meta_pixel_id') . '/events') . '?access_token=' . urlencode((string) AppSetting::get('meta_capi_token')), $body);
        IntegrationLog::write('meta_capi', 'purchase', $res->ok() ? 'ok' : 'error', $res->ok() ? null : mb_substr($res->body(), 0, 1000), $shown, $o->code);
    }
}
