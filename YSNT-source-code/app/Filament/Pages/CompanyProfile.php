<?php
namespace App\Filament\Pages;

use App\Filament\SettingsPage;
use App\Support\Company;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CompanyProfile extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Hồ sơ doanh nghiệp';
    protected static ?string $title = 'Hồ sơ doanh nghiệp';
    protected static ?string $slug = 'ho-so-doanh-nghiep';

    public function sections(): array
    {
        $d = Company::DEFAULTS;
        return [
            'Giấy phép kinh doanh' => ['desc' => 'Dùng cho chân trang web, bill, hóa đơn, xác thực Zalo OA và các sàn.', 'fields' => [
                'company_name' => ['label' => 'Tên công ty đúng trên giấy phép', 'default' => $d['company_name'], 'wide' => true],
                'tax_code' => ['label' => 'Mã số thuế', 'default' => $d['tax_code']],
                'tax_issued' => ['label' => 'Ngày cấp, nơi cấp', 'default' => $d['tax_issued']],
                'hq_address' => ['label' => 'Địa chỉ trụ sở', 'default' => $d['hq_address'], 'wide' => true],
                'legal_rep' => ['label' => 'Người đại diện theo pháp luật'],
                'legal_rep_title' => ['label' => 'Chức danh', 'placeholder' => 'Giám đốc'],
                'gpkd_file' => ['label' => 'Bản scan giấy phép kinh doanh', 'type' => 'file', 'hint' => 'PDF hoặc ảnh, tối đa 10MB. Lưu riêng tư, chỉ quản trị xem được.'],
            ]],
            'Hotline, email nhận OTP' => ['desc' => 'Số và email nhận OTP chỉ lưu nội bộ, không hiện ra web.', 'fields' => [
                'hotline_sales' => ['label' => 'Hotline bán hàng', 'default' => $d['hotline_sales'], 'hint' => 'Hiện trên web, bill, trợ lý AI.'],
                'hotline_otp' => ['label' => 'Số di động nhận OTP', 'hint' => 'Số đăng ký Zalo OA, TikTok Shop, sàn, Meta.'],
                'email_public' => ['label' => 'Email liên hệ hiện trên web', 'default' => $d['email_public']],
                'email_otp' => ['label' => 'Email công ty nhận OTP', 'hint' => 'Email đăng ký tài khoản các nền tảng.'],
                'showroom_address' => ['label' => 'Địa chỉ nhà yến, showroom', 'default' => $d['showroom_address'], 'wide' => true],
            ]],
            'Bộ nhận diện thương hiệu' => ['desc' => 'Có logo thì web, trang quản trị và bill dùng logo; có màu chính thì nút và tiêu đề đổi theo.', 'fields' => [
                'brand_name' => ['label' => 'Tên thương hiệu', 'default' => 'Yến Sào Nha Trang'],
                'brand_tagline' => ['label' => 'Câu dưới logo', 'default' => 'Từ nhà yến Khánh Hòa'],
                'brand_logo' => ['label' => 'Logo', 'type' => 'image', 'hint' => 'PNG nền trong, cao ít nhất 120px.'],
                'brand_color' => ['label' => 'Màu chính', 'type' => 'color', 'hint' => 'Để trống thì giữ màu hiện tại.'],
            ]],
            'Mạng xã hội, gian hàng' => ['desc' => 'Đường dẫn trang chính thức. Hiện ở chân trang và giúp Google nhận ra các trang này là của công ty.', 'fields' => [
                'social_facebook' => ['label' => 'Fanpage Facebook', 'placeholder' => 'https://facebook.com/...'],
                'social_instagram' => ['label' => 'Instagram', 'placeholder' => 'https://instagram.com/...'],
                'social_threads' => ['label' => 'Threads', 'placeholder' => 'https://threads.net/@...'],
                'social_tiktok' => ['label' => 'TikTok', 'placeholder' => 'https://tiktok.com/@...'],
                'social_youtube' => ['label' => 'YouTube'],
                'social_zalo' => ['label' => 'Zalo OA', 'placeholder' => 'https://zalo.me/...'],
                'social_shopee' => ['label' => 'Gian hàng Shopee'],
                'social_lazada' => ['label' => 'Gian hàng Lazada'],
            ]],
            'Tên miền, máy chủ' => ['desc' => 'Chỉ ghi nơi quản lý và tên đăng nhập. <b>Mật khẩu không lưu ở đây</b>; khi chuyển máy chủ, mật khẩu được nhập trực tiếp lúc chạy lệnh chuyển.', 'fields' => [
                'domain_main' => ['label' => 'Tên miền chính', 'placeholder' => 'ysnt.com.vn'],
                'domain_registrar' => ['label' => 'Nơi quản lý tên miền (link)', 'placeholder' => 'https://...'],
                'domain_account' => ['label' => 'Tài khoản quản lý tên miền'],
                'vps_provider' => ['label' => 'Nhà cung cấp VPS, hosting (link)'],
                'vps_ip' => ['label' => 'Địa chỉ IP máy chủ'],
                'vps_account' => ['label' => 'Tài khoản đăng nhập VPS'],
            ]],
            'Thẻ thanh toán dịch vụ online' => ['desc' => '<b>Không nhập số thẻ.</b> Chỉ ghi đã có thẻ chưa và ai giữ để gia hạn dịch vụ.', 'fields' => [
                'card_ready' => ['label' => 'Đã có thẻ tín dụng doanh nghiệp', 'type' => 'toggle'],
                'card_note' => ['label' => 'Ghi chú (ngân hàng, người giữ thẻ)'],
            ]],
        ];
    }
}
