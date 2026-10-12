<?php
namespace App\Hr;

use App\Models\Ambassador;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Support\Company;
use App\Support\Mailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Staff account actions shared by the admin pages: import, password, approve, leave (go365 behaviour). */
class Staff
{
    /** readable temporary password; the employee must change it at first login */
    public static function resetPassword(Employee $e): string
    {
        $plain = Str::upper(Str::random(3)) . random_int(1000, 9999) . Str::lower(Str::random(2));
        $e->forceFill(['password' => $plain, 'must_change_password' => true])->save();
        return $plain;
    }

    public static function approve(Employee $e): string
    {
        $e->update(['onboarding_status' => 'approved', 'status' => 'active']);
        return self::resetPassword($e);
    }

    /** Active -> inactive: Workplace access ends on the next click, referral code stops, employee and HR get an email */
    public static function leave(Employee $e, ?string $type = null, ?string $lastDay = null, ?string $reason = null): void
    {
        $e->update(['status' => 'inactive', 'termination_type' => $type ?? $e->termination_type ?? 'resign',
            'last_working_day' => $lastDay ?? $e->last_working_day ?? now()->toDateString(), 'termination_reason' => $reason ?? $e->termination_reason]);
        self::afterLeave($e->fresh());
    }

    public static function afterLeave(Employee $e): void
    {
        $e->ambassador?->update(['status' => 'inactive']);
        $day = $e->last_working_day?->format('d/m/Y') ?? now()->format('d/m/Y');
        if ($e->email) Mailer::send($e->email, '[' . Company::get('brand_name') . '] Thông báo nghỉ việc', view('emails.simple', ['lines' => ["Chào {$e->full_name},",
            ($e->termination_type === 'dismissed' ? 'Công ty thông báo chấm dứt hợp đồng lao động với bạn.' : 'Công ty xác nhận đơn nghỉ việc của bạn.') . " Ngày làm việc cuối cùng: $day.",
            'Tài khoản Workplace của bạn đã ngưng. Cảm ơn bạn đã đồng hành cùng ' . Company::get('company_name') . '.']])->render(), 'staff_leave', $e->code);
        foreach (Hr::notifyEmails() as $to) Mailer::send($to, '[' . Company::get('brand_name') . ' HR] Nghỉ việc: ' . $e->full_name, view('emails.simple', ['lines' => ['Nhân viên nghỉ việc, kiểm tra bàn giao.'],
            'rows' => ['Nhân viên' => $e->full_name . ' (' . $e->code . ')', 'Phòng ban' => (string) $e->department?->name, 'Loại' => \App\Models\Employee::TERMINATION[$e->termination_type] ?? '',
                'Ngày làm cuối' => $day, 'Lý do' => (string) $e->termination_reason, 'Bàn giao' => (string) $e->asset_return_note]])->render(), 'staff_leave_hr', $e->code);
    }

    public static function back(Employee $e): void { $e->ambassador?->update(['status' => 'active']); }

    /**
     * Rows from an Excel / CSV sheet. Header names (any order, accents optional): ho ten, so dien thoai, email, phong ban, chuc danh, ngay vao lam.
     * @return array{created:int,skipped:array<int,string>}
     */
    public static function import(string $path): array
    {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        $head = array_map(fn ($h) => Str::slug(Str::ascii((string) $h), '_'), array_shift($sheet) ?? []);
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $head, true))->first(fn ($i) => $i !== false);
        $c = ['name' => $col(['ho_ten', 'ho_va_ten', 'ten', 'full_name']), 'phone' => $col(['so_dien_thoai', 'sdt', 'dien_thoai', 'phone']), 'email' => $col(['email']),
            'dept' => $col(['phong_ban', 'bo_phan', 'department']), 'title' => $col(['chuc_danh', 'chuc_vu', 'vi_tri', 'job_title']), 'hire' => $col(['ngay_vao_lam', 'ngay_vao', 'hire_date'])];
        if ($c['name'] === null || $c['phone'] === null) return ['created' => 0, 'skipped' => ['Thiếu cột "Họ tên" hoặc "Số điện thoại" ở dòng tiêu đề']];
        $created = 0; $skipped = [];
        foreach ($sheet as $i => $row) {
            $name = trim((string) ($row[$c['name']] ?? '')); $phone = Customer::normalizePhone((string) ($row[$c['phone']] ?? ''));
            if ($name === '' && ! $phone) continue;
            if (! $phone) { $skipped[] = 'Dòng ' . ($i + 2) . ": $name, số điện thoại không hợp lệ"; continue; }
            if (Employee::where('phone', $phone)->exists()) { $skipped[] = 'Dòng ' . ($i + 2) . ": $name, số $phone đã có"; continue; }
            $dept = ($d = trim((string) ($row[$c['dept']] ?? ''))) !== '' && $c['dept'] !== null ? Department::firstOrCreate(['name' => mb_substr($d, 0, 64)]) : null;
            $hire = null;
            if ($c['hire'] !== null && ($h = $row[$c['hire']] ?? null)) {
                try { $hire = is_numeric($h) ? Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($h))->toDateString() : Carbon::createFromFormat('d/m/Y', trim($h))->toDateString(); } catch (\Throwable) {}
            }
            Employee::create(['full_name' => $name, 'phone' => $phone, 'email' => $c['email'] !== null ? (filter_var($row[$c['email']] ?? '', FILTER_VALIDATE_EMAIL) ?: null) : null,
                'department_id' => $dept?->id, 'job_title' => $c['title'] !== null ? (trim((string) ($row[$c['title']] ?? '')) ?: null) : null, 'hire_date' => $hire, 'status' => 'active']);
            $created++;
        }
        return ['created' => $created, 'skipped' => $skipped];
    }
}
