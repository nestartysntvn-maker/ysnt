<?php
namespace App\Filament\Pages;

use App\Filament\SettingsPage;
use App\Support\Seo;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SeoSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;
    protected static string|UnitEnum|null $navigationGroup = 'SEO';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Cấu hình SEO';
    protected static ?string $title = 'Cấu hình SEO';
    protected static ?string $slug = 'seo';

    public static function canAccess(): bool { return in_array(auth()->user()?->role, ['admin', 'content'], true); }

    public function banner(): ?array
    {
        return Seo::indexable() ? ['tone' => 'ok', 'html' => '<b>Google đang được phép lập chỉ mục</b> các trang không gắn "ẩn khỏi Google".']
            : ['tone' => 'warn', 'html' => '<b>Đang chặn Google lập chỉ mục</b> (đúng cho bản chạy thử). Bật khi web đã chạy trên tên miền chính.'];
    }

    public function sections(): array
    {
        return [
            'Lập chỉ mục' => ['fields' => [
                'seo_allow_index' => ['label' => 'Cho Google, Bing lập chỉ mục web', 'type' => 'toggle', 'hint' => 'Chỉ bật trên tên miền chính. Bản chạy thử để tắt.'],
                'seo_sitemap' => ['label' => 'Sitemap (khai báo trong Search Console)', 'type' => 'info', 'value' => url('/sitemap.xml')],
            ]],
            'Xác minh, đo lường' => ['desc' => 'Điền mã nào thì web tự gắn mã đó. Để trống thì không gắn gì.', 'fields' => [
                'seo_google_verify' => ['label' => 'Mã xác minh Google Search Console', 'hint' => 'Chỉ phần content trong thẻ meta google-site-verification.'],
                'seo_bing_verify' => ['label' => 'Mã xác minh Bing Webmaster'],
                'seo_ga4_id' => ['label' => 'Google Analytics 4 (G-XXXX)', 'hint' => 'Tự ghi sự kiện mua hàng kèm mã đơn.'],
                'seo_gtm_id' => ['label' => 'Google Tag Manager (GTM-XXXX)'],
                'seo_gbp_url' => ['label' => 'Link Google Maps của nhà yến (Google Business Profile)', 'wide' => true],
            ]],
            'Mô tả mặc định' => ['fields' => [
                'seo_default_desc' => ['label' => 'Mô tả dùng khi trang chưa có mô tả riêng', 'type' => 'textarea',
                    'default' => 'Tổ yến từ nhà yến Khánh Hòa: tổ yến tinh chế, tổ yến ăn liền, tổ yến chưng sẵn, quà biếu. Giao toàn quốc, ghé nhà yến tại 01 Nguyễn Thiện Thuật, Nha Trang.'],
            ]],
        ];
    }
}
