<?php
namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use UnitEnum;

class ZaloSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;
    protected static string|UnitEnum|null $navigationGroup = 'Zalo OA';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Kết nối Zalo OA';
    protected static ?string $title = 'Kết nối Zalo OA';
    protected static ?string $slug = 'zalo';
    protected string $view = 'filament.pages.zalo-settings';
    public const KEYS = ['zalo_oa_id', 'zalo_app_id', 'zalo_app_secret', 'zalo_access_token', 'zalo_refresh_token', 'zalo_oa_secret'];
    public const SECRET = ['zalo_app_secret', 'zalo_access_token', 'zalo_refresh_token', 'zalo_oa_secret'];
    public array $f = [];
    public ?string $status = null;
    public bool $inboxLive = false;

    public static function canAccess(): bool { return auth()->user()?->role === 'admin'; }

    public function mount(): void
    {
        foreach (self::KEYS as $k) $this->f[$k] = in_array($k, self::SECRET, true) ? '' : (string) AppSetting::get($k, '');
        $this->inboxLive = AppSetting::enabled('zalo_inbox_live');
    }

    public function has(string $k): bool { return (bool) AppSetting::get($k); }

    public function save(): void
    {
        foreach (self::KEYS as $k) {
            $v = trim((string) ($this->f[$k] ?? ''));
            if (in_array($k, self::SECRET, true) && $v === '') continue;   // blank secret = keep the stored one
            AppSetting::put($k, $v);
        }
        if (trim((string) ($this->f['zalo_access_token'] ?? '')) !== '') AppSetting::put('zalo_token_expires_at', now()->addHour()->toIso8601String());
        AppSetting::put('zalo_inbox_live', $this->inboxLive ? '1' : '0');
        $this->mount();
        Notification::make()->title('Đã lưu thông tin kết nối')->success()->send();
    }

    public function test(): void
    {
        $token = AppSetting::get('zalo_access_token');
        if (! $token) { $this->status = 'Chưa có access token. Hệ thống đang ở chế độ chạy thử.'; return; }
        try {
            $j = Http::timeout(15)->withHeaders(['access_token' => $token])->get('https://openapi.zalo.me/v2.0/oa/getoa')->json() ?? [];
            $this->status = (int) ($j['error'] ?? -1) === 0 ? 'Kết nối được với OA: ' . ($j['data']['name'] ?? '') : 'Zalo báo lỗi: ' . ($j['message'] ?? 'không rõ');
        } catch (\Throwable $e) { $this->status = 'Không gọi được Zalo: ' . $e->getMessage(); }
    }
}
