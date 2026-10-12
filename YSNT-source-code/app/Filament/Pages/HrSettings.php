<?php
namespace App\Filament\Pages;

use App\Filament\SettingsPage;
use App\Hr\Hr;
use App\Support\Otp;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class HrSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Cấu hình nhân sự';
    protected static ?string $title = 'Cấu hình nhân sự, Workplace';
    protected static ?string $slug = 'cau-hinh-nhan-su';

    public static function canAccess(): bool { return auth()->user()?->role === 'admin'; }

    public function banner(): ?array
    {
        $t = Otp::template();
        return ['tone' => Otp::available() ? 'ok' : 'info', 'html' => '<b>Workplace:</b> ' . url('/noi-bo') . '<br><b>Đăng nhập OTP:</b> '
            . (Otp::znsReady() ? 'gửi qua Zalo ZNS' : (Otp::smsReady() ? 'gửi qua SMS' : 'chưa bật, nhân viên dùng mật khẩu.'))
            . ' Muốn dùng Zalo ZNS: đăng ký mẫu tin "' . e($t->name) . '" với Zalo, nhập mã mẫu và bật ở trang Mẫu tin ZNS.'];
    }

    public function sections(): array
    {
        $groups = [];
        foreach (Hr::GROUPS as $k => [$label, $def]) $groups['hr_group_' . $k] = ['label' => $label, 'type' => 'toggle', 'default' => $def];
        return [
            'Nhóm trường trong hồ sơ nhân sự' => ['desc' => 'Mọi nhóm đã có sẵn; bật nhóm nào thì hồ sơ, Workplace và trang tự điền hiện nhóm đó. Lương chỉ Kế toán và Quản trị xem; thông tin nhạy cảm chỉ Quản trị xem.', 'fields' => $groups],
            'Thông báo nhân sự, tuyển dụng' => ['fields' => [
                'hr_notify_email' => ['label' => 'Email nhận thông báo nhân sự (hồ sơ mới, lịch phỏng vấn, nghỉ việc)', 'hint' => 'Nhiều địa chỉ cách nhau dấu phẩy. Để trống: dùng email nhận báo đơn.', 'wide' => true],
                'recruit_intro' => ['label' => 'Lời giới thiệu đầu trang Tuyển dụng', 'type' => 'textarea'],
            ]],
            'Giao việc, nhắc việc' => ['desc' => 'Việc luôn hiện trên Workplace. Bật thêm kênh để người nhận được báo khi có việc mới, trước hạn và khi quá hạn (không gửi từ 21:00 đến 7:00).', 'fields' => [
                'task_notify_zalo' => ['label' => 'Nhắc qua Zalo OA (người đã quan tâm OA)', 'type' => 'toggle', 'default' => '1', 'hint' => 'Gửi thật khi đã bật gửi thật ở trang Kết nối Zalo OA.'],
                'task_notify_email' => ['label' => 'Nhắc qua email', 'type' => 'toggle', 'default' => '1', 'hint' => 'Gửi thật khi đã bật ở trang Máy chủ mail.'],
            ]],
            'Đăng nhập OTP bằng SMS (eSMS.vn)' => ['desc' => 'Chưa có Zalo ZNS thì có thể gửi mã qua SMS brandname.', 'fields' => [
                'sms_api_key' => ['label' => 'API key', 'type' => 'secret'],
                'sms_secret_key' => ['label' => 'Secret key', 'type' => 'secret'],
                'sms_brandname' => ['label' => 'Brandname đã đăng ký'],
                'sms_live' => ['label' => 'Bật gửi SMS thật', 'type' => 'toggle'],
            ]],
        ];
    }
}
