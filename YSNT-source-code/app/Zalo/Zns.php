<?php
namespace App\Zalo;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ZaloMessage;
use App\Models\ZaloTemplate;
use Illuminate\Support\Facades\Http;

/**
 * ZNS sender. Until the OA is connected and a template has a Zalo template id,
 * every send is recorded as "dry_run" with the exact payload that would have gone out.
 */
class Zns
{
    public const SEND_CHANNELS = ['web', 'phone', 'corporate'];   // POS and marketplace orders are not messaged

    public static function phone84(?string $p): ?string
    {
        $p = preg_replace('/\D/', '', (string) $p);
        return preg_match('/^0\d{9}$/', $p) ? '84' . substr($p, 1) : null;
    }

    /** value for one template param, from a mapping like "order.code" / "order.total_vnd" / "text:..." */
    public static function value(string $src, Order $o): string
    {
        if (str_starts_with($src, 'text:')) return substr($src, 5);
        return match ($src) {
            'order.code' => $o->code,
            'order.customer_name' => (string) ($o->ship_name ?: 'Quý khách'),
            'order.total_vnd' => number_format((int) $o->total, 0, ',', '.') . 'đ',
            'order.total' => (string) (int) $o->total,
            'order.date' => $o->created_at?->format('d/m/Y') ?? '',
            'order.items' => $o->items->map(fn ($i) => $i->product_name . ' x' . $i->qty)->implode(', '),
            'order.first_item' => (string) ($o->items->first()?->product_name ?? ''),
            'order.track_url' => url('/tra-cuu-don?ma=' . $o->code),
            default => '',
        };
    }

    public static function forOrder(string $code, Order $order): ?ZaloMessage
    {
        $tpl = ZaloTemplate::where('code', $code)->where('is_active', true)->first();
        if (! $tpl || ! in_array($order->channel, self::SEND_CHANNELS, true)) return null;
        if (ZaloMessage::where('zalo_template_id', $tpl->id)->where('order_id', $order->id)->exists()) return null; // never twice
        $order->loadMissing('items');
        $data = collect($tpl->params ?? [])->map(fn ($src) => mb_substr(self::value((string) $src, $order), 0, 100))->all();
        $phone = self::phone84($order->ship_phone);
        $cust = $order->customer_id ? Customer::find($order->customer_id) : null;
        $msg = ZaloMessage::create(['zalo_template_id' => $tpl->id, 'order_id' => $order->id, 'customer_id' => $cust?->id, 'phone' => $phone,
            'payload' => ['template_data' => $data], 'status' => 'queued']);
        if (! $phone) return tap($msg)->update(['status' => 'skipped', 'error' => 'Số điện thoại không hợp lệ']);
        if ($cust?->zalo_opt_out) return tap($msg)->update(['status' => 'skipped', 'error' => 'Khách đã từ chối nhận tin']);
        $token = AppSetting::get('zalo_access_token');
        if (! $token || ! $tpl->zns_template_id) {
            return tap($msg)->update(['status' => 'dry_run', 'error' => ! $token ? 'Chạy thử: chưa kết nối Zalo OA' : 'Chạy thử: mẫu chưa có mã Zalo duyệt']);
        }
        \App\Jobs\SendZns::dispatch($msg->id)->afterCommit();
        return $msg;
    }

    /** real API call; only reached when connected */
    public static function deliver(ZaloMessage $m): void
    {
        $tpl = $m->template;
        $res = Http::timeout(20)->withHeaders(['access_token' => (string) AppSetting::get('zalo_access_token')])
            ->post('https://business.openapi.zalo.me/message/template', [
                'phone' => $m->phone, 'template_id' => $tpl->zns_template_id,
                'template_data' => $m->payload['template_data'] ?? [], 'tracking_id' => 'ysnt-' . $m->id,
            ]);
        $j = $res->json() ?? [];
        if ($res->ok() && (int) ($j['error'] ?? -1) === 0) {
            $m->update(['status' => 'sent', 'zalo_msg_id' => $j['data']['msg_id'] ?? null, 'sent_at' => now(), 'error' => null]);
        } else {
            $m->update(['status' => 'failed', 'error' => mb_substr(($j['message'] ?? $res->body()) . ' (mã ' . ($j['error'] ?? $res->status()) . ')', 0, 500)]);
        }
    }

    /** OA access tokens expire; the refresh token rotates on every refresh */
    public static function refreshToken(): bool
    {
        $res = Http::asForm()->timeout(20)->withHeaders(['secret_key' => (string) AppSetting::get('zalo_app_secret')])
            ->post('https://oauth.zaloapp.com/v4/oa/access_token', [
                'refresh_token' => AppSetting::get('zalo_refresh_token'), 'app_id' => AppSetting::get('zalo_app_id'), 'grant_type' => 'refresh_token',
            ]);
        $j = $res->json() ?? [];
        if (empty($j['access_token'])) return false;
        AppSetting::put('zalo_access_token', $j['access_token']);
        AppSetting::put('zalo_refresh_token', $j['refresh_token'] ?? AppSetting::get('zalo_refresh_token'));
        AppSetting::put('zalo_token_expires_at', now()->addSeconds((int) ($j['expires_in'] ?? 3600))->toIso8601String());
        return true;
    }
}
