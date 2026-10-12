<?php
namespace App\Zalo;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/** Zalo OA customer-service messages (two-way chat), separate from ZNS templates */
class Oa
{
    public static function inboxLive(): bool { return AppSetting::enabled('zalo_inbox_live') && AppSetting::get('zalo_access_token'); }

    /** X-ZEvent-Signature: mac=sha256(appId + rawBody + timestamp + OA secret key) */
    public static function signatureOk(string $raw, ?string $header, ?string $timestamp): bool
    {
        $secret = (string) AppSetting::get('zalo_oa_secret');
        if ($secret === '' || ! $header) return false;
        $mac = hash('sha256', AppSetting::get('zalo_app_id') . $raw . $timestamp . $secret);
        return hash_equals('mac=' . $mac, trim($header));
    }

    /** @return array{0:string,1:?string,2:?string} [status, message id, error] */
    public static function sendText(?string $userId, string $text): array
    {
        if (! $userId) return ['failed', null, 'Thiếu mã người nhận Zalo'];
        if (! self::inboxLive()) return ['dry_run', null, 'Chạy thử: chưa bật gửi thật cho Zalo OA'];
        $res = Http::timeout(15)->withHeaders(['access_token' => (string) AppSetting::get('zalo_access_token')])
            ->post('https://openapi.zalo.me/v3.0/oa/message/cs', ['recipient' => ['user_id' => $userId], 'message' => ['text' => mb_substr($text, 0, 2000)]]);
        $j = $res->json() ?? [];
        return (int) ($j['error'] ?? -1) === 0 ? ['sent', $j['data']['message_id'] ?? null, null] : ['failed', null, mb_substr(($j['message'] ?? $res->body()) . ' (mã ' . ($j['error'] ?? $res->status()) . ')', 0, 480)];
    }
}
