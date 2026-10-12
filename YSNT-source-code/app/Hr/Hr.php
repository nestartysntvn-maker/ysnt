<?php
namespace App\Hr;

use App\Models\AppSetting;

/**
 * Who may see what (same split as go365: staff list / recruitment / salary / private data) and which optional field groups
 * are switched on. A group is built and stored already; turning it on only shows it ("cần gì thì mở").
 */
class Hr
{
    public const GROUPS = [
        'contact' => ['Liên hệ, kênh chat', '1'],
        'insurance' => ['Số BHXH, BHYT', '1'],
        'salary' => ['Lương, hoa hồng, phụ cấp', '0'],
        'private' => ['Thông tin cá nhân nhạy cảm: địa chỉ, ngày sinh, người liên hệ khẩn cấp, ngân hàng, CCCD, bằng cấp', '0'],
        'offboarding' => ['Nghỉ việc: loại, ngày làm cuối, lý do, bàn giao', '1'],
    ];
    /** permission => roles (admin always) */
    public const PERMS = ['staff' => ['hr'], 'recruit' => ['hr'], 'announce' => ['hr'], 'kb' => ['hr', 'content'], 'salary' => ['accountant'], 'private' => []];

    public static function open(string $group): bool { return AppSetting::get('hr_group_' . $group, self::GROUPS[$group][1] ?? '0') === '1'; }

    public static function can(string $perm): bool
    {
        $role = auth()->user()?->role;
        return $role === 'admin' || in_array($role, self::PERMS[$perm] ?? [], true);
    }

    /** demo / test Workplace accounts: never counted in growth, directory or company-wide announcements */
    public static function demoIds(): array { return array_values(array_filter(array_map('intval', explode(',', (string) AppSetting::get('demo_employee_ids'))))); }

    /** emails that get HR notices (new application, interview, leaver...) */
    public static function notifyEmails(): array
    {
        $list = AppSetting::get('hr_notify_email') ?: AppSetting::get('mail_order_to') ?: '';
        return array_values(array_filter(array_map('trim', explode(',', (string) $list)), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }
}
