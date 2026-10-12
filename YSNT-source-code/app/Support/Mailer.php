<?php
namespace App\Support;

use App\Models\AppSetting;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Mail;

/** SMTP from the settings page. Until "gửi thật" is on, every mail is only written to the integration log. */
class Mailer
{
    public static function ready(): bool { return AppSetting::get('mail_host') && AppSetting::get('mail_from_address'); }
    public static function live(): bool { return self::ready() && AppSetting::enabled('mail_live'); }

    private static function configure(): void
    {
        $enc = AppSetting::get('mail_encryption', 'tls');
        config(['mail.mailers.ysnt' => ['transport' => 'smtp', 'scheme' => $enc === 'ssl' ? 'smtps' : 'smtp', 'host' => AppSetting::get('mail_host'),
            'port' => (int) AppSetting::get('mail_port', $enc === 'ssl' ? '465' : '587'), 'username' => AppSetting::get('mail_username'),
            'password' => AppSetting::get('mail_password'), 'timeout' => 20, 'local_domain' => parse_url(config('app.url'), PHP_URL_HOST)],
            'mail.from' => ['address' => AppSetting::get('mail_from_address'), 'name' => AppSetting::get('mail_from_name') ?: Company::get('brand_name')]]);
    }

    public static function mask(string $email): string { [$u, $d] = array_pad(explode('@', $email, 2), 2, ''); return mb_substr($u, 0, 2) . '***@' . $d; }

    /** @return string ok | dry_run | error. $force = admin "gửi thử" button (still needs a configured server) */
    public static function send(string $to, string $subject, string $html, string $action, ?string $ref = null, bool $force = false): string
    {
        $payload = ['to' => self::mask($to), 'subject' => $subject];
        if (! ($force ? self::ready() : self::live())) {
            IntegrationLog::write('mail', $action, 'dry_run', self::ready() ? 'Chạy thử: chưa bật gửi thật' : 'Chạy thử: chưa cấu hình máy chủ mail', $payload, $ref);
            return 'dry_run';
        }
        try {
            self::configure();
            Mail::mailer('ysnt')->html($html, fn ($m) => $m->to($to)->subject($subject));
            IntegrationLog::write('mail', $action, 'ok', null, $payload, $ref);
            return 'ok';
        } catch (\Throwable $e) {
            IntegrationLog::write('mail', $action, 'error', $e->getMessage(), $payload, $ref);
            return 'error';
        }
    }
}
