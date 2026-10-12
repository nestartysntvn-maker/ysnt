<?php
namespace App\Filament\Pages;

use App\Filament\SettingsPage;
use App\Support\Company;
use App\Support\Mailer;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class MailSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Máy chủ mail';
    protected static ?string $title = 'Máy chủ mail';
    protected static ?string $slug = 'may-chu-mail';

    public function banner(): ?array
    {
        if (Mailer::live()) return ['tone' => 'ok', 'html' => '<b>Đang gửi thật.</b> Mail xác nhận đơn và báo đơn mới đi qua máy chủ bên dưới.'];
        return ['tone' => 'info', 'html' => '<b>Đang chạy thử.</b> Mail chỉ ghi vào "Nhật ký kết nối", không gửi đi. Nhập máy chủ, bấm "Gửi thư thử", thấy nhận được rồi mới bật gửi thật.'];
    }

    public function sections(): array
    {
        return [
            'Máy chủ gửi mail (SMTP)' => ['desc' => 'Lấy từ nhà cung cấp mail của công ty (Google Workspace, Hostinger, Zoho, mail theo tên miền...).', 'fields' => [
                'mail_host' => ['label' => 'Máy chủ SMTP', 'placeholder' => 'smtp.hostinger.com'],
                'mail_port' => ['label' => 'Cổng', 'placeholder' => '587 hoặc 465'],
                'mail_encryption' => ['label' => 'Mã hóa', 'type' => 'select', 'options' => ['tls' => 'TLS (cổng 587)', 'ssl' => 'SSL (cổng 465)'], 'default' => 'tls'],
                'mail_username' => ['label' => 'Tên đăng nhập'],
                'mail_password' => ['label' => 'Mật khẩu', 'type' => 'secret'],
                'mail_from_address' => ['label' => 'Gửi từ địa chỉ', 'placeholder' => 'no-reply@ysnt.com.vn'],
                'mail_from_name' => ['label' => 'Tên người gửi', 'default' => Company::get('brand_name')],
                'mail_order_to' => ['label' => 'Nhận báo đơn mới', 'hint' => 'Nhiều địa chỉ cách nhau dấu phẩy.'],
                'mail_live' => ['label' => 'Bật gửi thật', 'type' => 'toggle', 'hint' => 'Tắt thì mọi thư chỉ ghi nhật ký.'],
            ]],
            'Gửi thư thử' => ['fields' => ['mail_test_to' => ['label' => 'Gửi thư thử tới', 'placeholder' => 'email của anh']]],
        ];
    }

    public function buttons(): array { return ['sendTest' => 'Gửi thư thử']; }

    public function sendTest(): void
    {
        $to = trim((string) ($this->f['mail_test_to'] ?? ''));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) { $this->status = 'Nhập email nhận thư thử.'; return; }
        if (! Mailer::ready()) { $this->status = 'Chưa lưu máy chủ SMTP và địa chỉ gửi.'; return; }
        $r = Mailer::send($to, 'Thư thử từ hệ thống ' . Company::get('brand_name'), '<p>Máy chủ mail đã kết nối được.</p>', 'test', null, true);
        $this->status = $r === 'ok' ? 'Đã gửi. Kiểm tra hộp thư ' . $to . ' (cả mục Spam).' : 'Chưa gửi được. Xem lỗi ở "Nhật ký kết nối".';
    }
}
