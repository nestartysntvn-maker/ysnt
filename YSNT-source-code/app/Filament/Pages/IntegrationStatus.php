<?php
namespace App\Filament\Pages;

use App\Ai\Ai;
use App\Channels\Connectors\Marketplace;
use App\Meta\Meta;
use App\Models\AppSetting;
use App\Models\SalesChannel;
use App\Support\Mailer;
use App\Zalo\Oa;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** The checklist from "Thông tin cần thiết": what is in, what is missing, what already runs for real. */
class IntegrationStatus extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static string|UnitEnum|null $navigationGroup = 'Kết nối & cấu hình';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Tình trạng kết nối';
    protected static ?string $title = 'Tình trạng kết nối';
    protected static ?string $slug = 'tinh-trang-ket-noi';
    protected string $view = 'filament.pages.integration-status';
    public array $notes = [];

    public static function canAccess(): bool { return auth()->user()?->role === 'admin'; }

    private static function has(string ...$keys): bool { foreach ($keys as $k) if (! AppSetting::get($k)) return false; return true; }
    private static function st(bool $done, bool $partial = false): string { return $done ? 'done' : ($partial ? 'partial' : 'missing'); }

    private static function channel(string $platform): array
    {
        $c = SalesChannel::where('platform', $platform)->orderByRaw("status = 'active' desc")->first();
        $ready = $c && Marketplace::ready($c);
        return [self::st($ready && $c->status === 'active', (bool) $c), $ready && $c->status === 'active' ? 'Kéo đơn tự động' : ($c ? 'Chờ cấp quyền gian hàng' : 'Chưa tạo gian hàng')];
    }

    /** @return array<string, array<int, array{code:string,label:string,status:string,live:string,url:string}>> */
    public static function groups(): array
    {
        $profile = CompanyProfile::getUrl(); $channels = \App\Filament\Resources\SalesChannels\SalesChannelResource::getUrl();
        $ai = fn (array $ps) => collect($ps)->contains(fn ($p) => AppSetting::get("ai_key_$p"));
        [$tt, $ttLive] = self::channel('tiktok'); [$sp, $spLive] = self::channel('shopee'); [$lz, $lzLive] = self::channel('lazada');
        return [
            'A. Thông tin cần thiết' => [
                ['code' => 'gpkd', 'label' => '1. Scan giấy phép kinh doanh', 'status' => self::st(self::has('gpkd_file', 'legal_rep'), self::has('gpkd_file')), 'live' => '', 'url' => $profile],
                ['code' => 'hotline', 'label' => '2. Hotline bán hàng + số nhận OTP', 'status' => self::st(self::has('hotline_otp')), 'live' => '', 'url' => $profile],
                ['code' => 'email_otp', 'label' => '3. Email công ty nhận OTP', 'status' => self::st(self::has('email_otp')), 'live' => '', 'url' => $profile],
                ['code' => 'domain', 'label' => '4. Tài khoản tên miền', 'status' => self::st(self::has('domain_main', 'domain_account'), self::has('domain_main')), 'live' => '', 'url' => $profile],
                ['code' => 'vps', 'label' => '5. VPS, hosting và dịch vụ tên miền', 'status' => self::st(self::has('vps_ip', 'vps_account'), self::has('vps_provider') || self::has('vps_ip')), 'live' => '', 'url' => $profile],
                ['code' => 'zalo', 'label' => '6. Zalo OA', 'status' => self::st(self::has('zalo_access_token', 'zalo_oa_secret'), self::has('zalo_oa_id') || self::has('zalo_access_token')),
                    'live' => Oa::inboxLive() ? 'Hộp thư gửi thật' : 'Chạy thử', 'url' => ZaloSettings::getUrl()],
                ['code' => 'tiktok', 'label' => '6. TikTok Shop', 'status' => $tt, 'live' => $ttLive, 'url' => $channels],
                ['code' => 'facebook', 'label' => '7. Fanpage Facebook', 'status' => self::st(self::has('meta_page_id', 'meta_page_token', 'meta_app_secret'), self::has('meta_page_id') || self::has('social_facebook')),
                    'live' => Meta::inboxLive() ? 'Hộp thư gửi thật' : 'Chạy thử', 'url' => MetaSettings::getUrl()],
                ['code' => 'instagram', 'label' => '7. Instagram', 'status' => self::st(self::has('meta_ig_id', 'meta_page_token'), self::has('social_instagram')), 'live' => '', 'url' => MetaSettings::getUrl()],
                ['code' => 'threads', 'label' => '7. Threads', 'status' => self::st(self::has('social_threads')), 'live' => '', 'url' => $profile],
                ['code' => 'mail', 'label' => '8. Server mail', 'status' => self::st(Mailer::live(), Mailer::ready()), 'live' => Mailer::live() ? 'Gửi thật' : 'Chạy thử', 'url' => MailSettings::getUrl()],
                ['code' => 'brand', 'label' => '9. Bộ nhận diện (logo, màu)', 'status' => self::st(self::has('brand_logo', 'brand_color'), self::has('brand_logo') || self::has('brand_color')), 'live' => '', 'url' => $profile],
                ['code' => 'card', 'label' => '10. Thẻ tín dụng doanh nghiệp', 'status' => self::st(AppSetting::enabled('card_ready')), 'live' => '', 'url' => $profile],
            ],
            'B. Dịch vụ đề xuất' => [
                ['code' => 'ai_claude', 'label' => '1. Claude (API)', 'status' => self::st($ai(['claude'])), 'live' => '', 'url' => AiSettings::getUrl()],
                ['code' => 'ai_cn', 'label' => '1. Qwen hoặc DeepSeek (API)', 'status' => self::st($ai(['qwen', 'deepseek'])), 'live' => '', 'url' => AiSettings::getUrl()],
                ['code' => 'ai_other', 'label' => '2. Gemini hoặc ChatGPT', 'status' => self::st($ai(['gemini', 'openai'])), 'live' => '', 'url' => AiSettings::getUrl()],
                ['code' => 'ai_use', 'label' => 'Trợ lý tư vấn đang dùng', 'status' => self::st(Ai::ready('advisor')), 'live' => Ai::PROVIDERS[Ai::provider('advisor')], 'url' => AiSettings::getUrl()],
            ],
            'Sàn và đo lường' => [
                ['code' => 'shopee', 'label' => 'Shopee', 'status' => $sp, 'live' => $spLive, 'url' => $channels],
                ['code' => 'lazada', 'label' => 'Lazada', 'status' => $lz, 'live' => $lzLive, 'url' => $channels],
                ['code' => 'pixel', 'label' => 'Meta Pixel, Conversions API', 'status' => self::st(Meta::capiReady() && AppSetting::enabled('meta_capi_live'), self::has('meta_pixel_id')), 'live' => AppSetting::enabled('meta_capi_live') ? 'Gửi thật' : 'Chạy thử', 'url' => MetaSettings::getUrl()],
                ['code' => 'gsc', 'label' => 'Google Search Console', 'status' => self::st(self::has('seo_google_verify')), 'live' => '', 'url' => SeoSettings::getUrl()],
                ['code' => 'ga4', 'label' => 'Google Analytics 4', 'status' => self::st(self::has('seo_ga4_id') || self::has('seo_gtm_id')), 'live' => '', 'url' => SeoSettings::getUrl()],
                ['code' => 'gbp', 'label' => 'Google Maps nhà yến', 'status' => self::st(self::has('seo_gbp_url')), 'live' => '', 'url' => SeoSettings::getUrl()],
            ],
            'Nhân sự, Workplace' => [
                ['code' => 'staff', 'label' => 'Danh sách nhân viên trên hệ thống', 'status' => self::st(\App\Models\Employee::whereNotIn('id', \App\Hr\Hr::demoIds())->count() >= 10, \App\Models\Employee::whereNotIn('id', \App\Hr\Hr::demoIds())->exists()), 'live' => \App\Models\Employee::where('status', 'active')->whereNotIn('id', \App\Hr\Hr::demoIds())->count() . ' người đang làm', 'url' => \App\Filament\Resources\Employees\EmployeeResource::getUrl()],
                ['code' => 'otp', 'label' => 'Đăng nhập OTP (Zalo ZNS hoặc SMS)', 'status' => self::st(\App\Support\Otp::available()), 'live' => \App\Support\Otp::available() ? 'Đang bật' : 'Dùng mật khẩu', 'url' => HrSettings::getUrl()],
                ['code' => 'kb', 'label' => 'Quy trình cho AI giải đáp nội bộ', 'status' => self::st(\App\Models\KbDoc::where('is_published', true)->count() >= 5, \App\Models\KbDoc::exists()), 'live' => \App\Models\KbDoc::where('is_published', true)->count() . ' tài liệu', 'url' => \App\Filament\Resources\KbDocs\KbDocResource::getUrl()],
            ],
        ];
    }

    public function mount(): void
    {
        foreach (self::groups() as $rows) foreach ($rows as $r) $this->notes[$r['code']] = (string) AppSetting::get('note_' . $r['code'], '');
    }

    public function saveNotes(): void
    {
        foreach ($this->notes as $code => $v) if (preg_match('/^[a-z_]+$/', $code)) AppSetting::put('note_' . $code, mb_substr(trim((string) $v), 0, 300));
        Notification::make()->title('Đã lưu ghi chú')->success()->send();
    }
}
