<?php
namespace App\Support;

use App\Models\AppSetting;
use App\Models\IntegrationLog;
use App\Models\ZaloTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time login codes for the staff Workplace. Sent by Zalo ZNS (template "staff_otp") or SMS brandname (eSMS);
 * whichever is connected first. Neither connected = OTP login is hidden and staff use phone + password.
 */
class Otp
{
    public const TTL = 300;
    public const TEMPLATE = 'staff_otp';

    public static function template(): ZaloTemplate
    {
        return ZaloTemplate::firstOrCreate(['code' => self::TEMPLATE], ['name' => 'Mã đăng nhập Workplace', 'trigger' => 'otp', 'params' => ['otp' => 'otp'],
            'sample_text' => 'Mã đăng nhập Workplace của bạn là <otp>. Mã có hiệu lực 5 phút, không chia sẻ cho ai.', 'is_active' => false]);
    }

    public static function znsReady(): bool { $t = self::template(); return $t->is_active && $t->zns_template_id && AppSetting::get('zalo_access_token'); }
    public static function smsReady(): bool { return AppSetting::enabled('sms_live') && AppSetting::get('sms_api_key') && AppSetting::get('sms_secret_key'); }
    public static function available(): bool { return self::znsReady() || self::smsReady(); }

    /** @return string|null error message for the user, null when a code was sent */
    public static function send(string $phone): ?string
    {
        $key = 'otp-send:' . $phone;
        if (RateLimiter::tooManyAttempts($key, 3)) return 'Bạn đã yêu cầu nhiều lần. Thử lại sau ' . ceil(RateLimiter::availableIn($key) / 60) . ' phút.';
        if (! self::available()) return 'Đăng nhập bằng mã OTP chưa được bật. Dùng mật khẩu.';
        RateLimiter::hit($key, 900);
        $code = (string) random_int(100000, 999999);
        Cache::put('otp:' . $phone, ['h' => Hash::make($code), 'n' => 0], self::TTL);
        $masked = substr($phone, 0, 3) . '****' . substr($phone, -3);
        [$via, $ok, $err] = self::znsReady() ? ['zns', ...self::viaZns($phone, $code)] : ['sms', ...self::viaSms($phone, $code)];
        IntegrationLog::write('otp', $via, $ok ? 'ok' : 'error', $err, ['to' => $masked]);
        return $ok ? null : 'Chưa gửi được mã, thử lại hoặc dùng mật khẩu.';
    }

    public static function check(string $phone, string $code): bool
    {
        $c = Cache::get('otp:' . $phone);
        if (! $c || $c['n'] >= 5) return false;
        if (Hash::check($code, $c['h'])) { Cache::forget('otp:' . $phone); return true; }
        Cache::put('otp:' . $phone, ['h' => $c['h'], 'n' => $c['n'] + 1], self::TTL);
        return false;
    }

    /** @return array{0:bool,1:?string} */
    private static function viaZns(string $phone, string $code): array
    {
        $res = Http::timeout(15)->withHeaders(['access_token' => (string) AppSetting::get('zalo_access_token')])->post('https://business.openapi.zalo.me/message/template', [
            'phone' => \App\Zalo\Zns::phone84($phone), 'template_id' => self::template()->zns_template_id, 'template_data' => ['otp' => $code], 'tracking_id' => 'otp-' . uniqid()]);
        $j = $res->json() ?? [];
        return (int) ($j['error'] ?? -1) === 0 ? [true, null] : [false, mb_substr(($j['message'] ?? $res->body()) . ' (mã ' . ($j['error'] ?? $res->status()) . ')', 0, 300)];
    }

    /** eSMS.vn brandname SMS (SmsType 2 = brandname customer care) */
    private static function viaSms(string $phone, string $code): array
    {
        $res = Http::timeout(15)->post('https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/', [
            'ApiKey' => AppSetting::get('sms_api_key'), 'SecretKey' => AppSetting::get('sms_secret_key'), 'Phone' => $phone, 'SmsType' => '2',
            'Brandname' => AppSetting::get('sms_brandname'), 'IsUnicode' => '0',
            'Content' => $code . ' la ma dang nhap Workplace ' . \Illuminate\Support\Str::ascii(Company::get('brand_name')) . '. Ma co hieu luc 5 phut.']);
        $j = $res->json() ?? [];
        return (string) ($j['CodeResult'] ?? '') === '100' ? [true, null] : [false, mb_substr('eSMS: ' . ($j['ErrorMessage'] ?? $res->body()), 0, 300)];
    }
}
