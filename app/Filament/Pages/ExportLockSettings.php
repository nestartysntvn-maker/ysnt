<?php
namespace App\Filament\Pages;

use App\Filament\SettingsPage;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ExportLock;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Hệ thống > Khóa xuất file: lock every "Xuất Excel" button; Quản trị always can, staff only when switched on here. */
class ExportLockSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;
    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Khóa xuất file';
    protected static ?string $title = 'Khóa xuất file';
    protected static ?string $slug = 'khoa-xuat-file';

    public static function canAccess(): bool { return auth()->user()?->role === 'admin'; }

    public function banner(): ?array
    {
        $locked = ExportLock::locked();
        $logs = ActivityLog::with('user')->where('action', 'export')->latest('id')->limit(8)->get();
        $html = $locked
            ? '<b>Đang khóa xuất file.</b> Chỉ Quản trị và người được bật bên dưới mới tải được file Excel.'
            : '<b>Đang mở.</b> Ai vào được trang nào thì xuất được file Excel của trang đó.';
        $html .= '<br><br><b>Lần xuất gần đây</b><br>' . ($logs->isEmpty() ? 'Chưa có lần xuất file nào.' : $logs->map(fn ($l) => e(($l->user?->name ?? 'Không rõ') . ' · '
            . (\App\Filament\Resources\ActivityLogs\ActivityLogResource::SUBJECTS[$l->subject_type] ?? $l->subject_type) . ' · ' . number_format((int) ($l->changes['rows'] ?? 0), 0, ',', '.') . ' dòng · '
            . $l->created_at?->format('H:i d/m/Y')))->implode('<br>'));
        return ['tone' => $locked ? 'ok' : 'warn', 'html' => $html];
    }

    public function sections(): array
    {
        $staff = User::where('is_active', true)->where('role', '!=', 'admin')->orderBy('name')->get();
        $people = [];
        foreach ($staff as $u) $people['export_allow_' . $u->id] = ['label' => $u->name . ' · ' . (User::ROLES[$u->role] ?? $u->role), 'type' => 'toggle', 'default' => '0'];
        return [
            'Khóa xuất file' => ['desc' => 'Áp dụng cho mọi nút Xuất Excel: Khách hàng, Đơn hàng, Sản phẩm, Hoa hồng. Mỗi lần xuất đều ghi vào Nhật ký thao tác: ai xuất, lúc nào, bao nhiêu dòng.', 'fields' => [
                'export_lock' => ['label' => 'Khóa xuất file', 'type' => 'toggle', 'default' => '1', 'hint' => 'Tắt công tắc này thì mọi người xuất được như trước.'],
            ]],
            'Người được xuất file khi đang khóa' => [
                'desc' => $people ? 'Tài khoản Quản trị luôn xuất được. Bật cho nhân viên nào thì người đó xuất được; tắt lại khi xong việc.'
                    : 'Tài khoản Quản trị luôn xuất được. Hiện chưa có tài khoản nhân viên nào; tạo ở trang Người dùng rồi bật quyền xuất ở đây khi cần.',
                'fields' => $people,
            ],
        ];
    }
}
