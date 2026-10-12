<?php
namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;

/**
 * Who may use the "Xuất Excel" buttons. Locked unless switched off at Hệ thống > Khóa xuất file:
 * then only Quản trị, plus the staff switched on there, can export.
 */
class ExportLock
{
    public static function locked(): bool
    {
        return AppSetting::get('export_lock', '1') !== '0';
    }

    public static function allows(?User $u): bool
    {
        if (! $u) return false;
        if ($u->role === 'admin' || ! self::locked()) return true;
        return AppSetting::enabled('export_allow_' . $u->id);
    }
}
