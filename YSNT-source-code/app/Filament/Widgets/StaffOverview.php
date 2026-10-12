<?php
namespace App\Filament\Widgets;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Hr\Hr;
use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;
    protected ?string $heading = 'Nhân sự';
    protected ?string $pollingInterval = null;

    public static function canView(): bool { return Hr::can('staff'); }

    protected function getStats(): array
    {
        $base = fn () => Employee::whereNotIn('employees.id', Hr::demoIds());
        $active = $base()->where('employees.status', 'active')->where(fn ($w) => $w->whereNull('employees.onboarding_status')->orWhere('employees.onboarding_status', '!=', 'pending'));
        $n = (clone $active)->count();
        $byDept = (clone $active)->leftJoin('departments as d', 'd.id', '=', 'employees.department_id')->selectRaw("COALESCE(d.name, 'Chưa xếp phòng') AS dn, COUNT(*) AS c")
            ->groupBy('dn')->orderByDesc('c')->limit(4)->pluck('c', 'dn')->map(fn ($c, $d) => "$d $c")->implode(' · ');
        $used = (clone $active)->whereNotNull('last_login_at')->count();
        $pending = $base()->where('onboarding_status', 'pending')->count();
        $joined = $base()->where('hire_date', '>=', now()->startOfMonth()->toDateString())->count();
        $left = $base()->where('status', '!=', 'active')->where('terminated_at', '>=', now()->startOfMonth())->count();
        $url = EmployeeResource::getUrl();
        return [
            Stat::make('Nhân sự đang làm', number_format($n, 0, ',', '.'))->description($byDept ?: 'Chưa nhập danh sách nhân viên')->url($url),
            Stat::make('Đã vào Workplace', $n ? round($used / $n * 100) . '%' : '0%')->description("$used / $n người đã đăng nhập ít nhất một lần")->url($url),
            Stat::make('Hồ sơ chờ duyệt', $pending)->description('Người mới tự điền hồ sơ')->color($pending ? 'warning' : 'gray')->url($url),
            Stat::make('Biến động tháng này', '+' . $joined . ' / −' . $left)->description('Vào làm / nghỉ việc'),
        ];
    }
}
