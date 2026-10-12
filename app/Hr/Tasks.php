<?php
namespace App\Hr;

use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Support\Company;
use App\Support\Mailer;
use App\Zalo\Oa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Giving work to a person: one task row per assignee, a note on the Workplace, Zalo OA + email messages
 * (dry run until those channels are switched on), reminders before the deadline and once when overdue.
 */
class Tasks
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    /** people this employee may give work to: direct reports, and the whole department when they head it */
    public static function assignable(Employee $m)
    {
        $headOf = \App\Models\Department::where('head_employee_id', $m->id)->pluck('id');
        return Employee::where('status', 'active')->where('id', '!=', $m->id)->whereNotIn('id', Hr::demoIds() ?: [0])
            ->where(fn ($w) => $w->where('manager_id', $m->id)->orWhereIn('department_id', $headOf->all() ?: [0]))->orderBy('full_name')->get();
    }

    /** @param int[] $employeeIds @return Task[] */
    public static function assign(array $employeeIds, array $data, ?User $byUser = null, ?Employee $byEmployee = null): array
    {
        $batch = count($employeeIds) > 1 ? (string) Str::uuid() : null; $out = [];
        foreach (array_unique(array_map('intval', $employeeIds)) as $id) {
            $t = Task::create(['title' => $data['title'], 'description' => $data['description'] ?? null, 'link' => $data['link'] ?? null, 'employee_id' => $id,
                'assigned_by_user_id' => $byUser?->id, 'assigned_by_employee_id' => $byEmployee?->id, 'batch' => $batch, 'priority' => $data['priority'] ?? 'normal',
                'due_at' => $data['due_at'] ?? null, 'remind_before_hours' => (int) ($data['remind_before_hours'] ?? 24), 'status' => 'todo']);
            self::log($t, 'status', 'Giao việc', $byEmployee, $byUser);
            self::notifyAssignee($t, 'Việc mới', 'Người giao: ' . $t->assignerName() . ($t->due_at ? "\nHạn: " . self::when($t->due_at) : ''));
            $out[] = $t;
        }
        return $out;
    }

    public static function when(?Carbon $d): string { return $d ? $d->copy()->tz(self::TZ)->format('H:i d/m/Y') : 'không đặt hạn'; }

    public static function log(Task $t, string $kind, ?string $body, ?Employee $e = null, ?User $u = null, ?string $channels = null): void
    {
        TaskUpdate::create(['task_id' => $t->id, 'employee_id' => $e?->id, 'user_id' => $u?->id, 'kind' => $kind, 'body' => $body, 'channels' => $channels]);
    }

    /** Zalo + email to one employee about one task; returns the channels that actually sent */
    private static function message(Employee $to, Task $t, string $head, string $body): string
    {
        $text = "[$head] {$t->title}\n$body\nXem trên Workplace: " . url('/noi-bo/viec');
        $sent = [];
        if (AppSetting::get('task_notify_zalo', '1') === '1' && ($zid = $to->zaloId())) { [$st] = Oa::sendText($zid, $text); $sent[] = 'zalo:' . $st; }
        if (AppSetting::get('task_notify_email', '1') === '1' && $to->email) {
            $sent[] = 'email:' . Mailer::send($to->email, "[$head] {$t->title}", view('emails.simple', ['lines' => ["Chào {$to->full_name},", "$head: {$t->title}", $body], 'button' => ['Mở Workplace', url('/noi-bo/viec')]])->render(), 'task_' . Str::slug($head, '_'), 'VIEC-' . $t->id);
        }
        return implode(', ', $sent) ?: 'workplace';
    }

    public static function notifyAssignee(Task $t, string $head, string $body): void
    {
        $t->loadMissing('employee');
        self::log($t, 'reminder', "$head: $body", null, null, self::message($t->employee, $t, $head, $body));
    }

    /** the person who gave the work: an employee gets Zalo/email, an admin user gets email */
    public static function notifyAssigner(Task $t, string $head, string $body): void
    {
        $t->loadMissing(['assignerEmployee', 'assignerUser', 'employee']);
        if ($t->assignerEmployee) { self::log($t, 'reminder', "Báo người giao: $head", null, null, self::message($t->assignerEmployee, $t, $head, $body)); return; }
        if ($t->assignerUser?->email) Mailer::send($t->assignerUser->email, "[$head] {$t->title}", view('emails.simple', ['lines' => ["$head: {$t->title}", $body], 'button' => ['Mở việc đã giao', url('/admin/tasks/' . $t->id . '/edit')]])->render(), 'task_assigner', 'VIEC-' . $t->id);
    }

    public static function setStatus(Task $t, string $status, ?string $note = null, ?Employee $by = null, ?User $byUser = null): void
    {
        if (! isset(Task::STATUS[$status]) || $t->status === $status) return;
        $t->update(['status' => $status, 'done_at' => $status === 'done' ? now() : null, 'done_note' => $status === 'done' ? ($note ?: $t->done_note) : $t->done_note]);
        self::log($t, 'status', Task::STATUS[$status] . ($note ? ": $note" : ''), $by, $byUser);
        if ($status === 'done' && $by) self::notifyAssigner($t, 'Đã xong', $t->employee->full_name . ' báo xong lúc ' . self::when(now()) . ($note ? "\nGhi chú: $note" : ''));
    }

    /** every 15 minutes: reminder before the deadline, one notice when it passes. Quiet from 21:00 to 7:00. */
    public static function sweep(): array
    {
        $h = (int) now(self::TZ)->format('G');
        if ($h < 7 || $h >= 21) return ['reminded' => 0, 'overdue' => 0, 'quiet' => true];
        $r = 0; $o = 0;
        foreach (Task::open()->whereNotNull('due_at')->whereNull('reminded_at')->where('due_at', '>', now())->with('employee')->get() as $t) {
            if ($t->due_at->copy()->subHours($t->remind_before_hours)->isFuture()) continue;
            self::notifyAssignee($t, 'Nhắc việc', 'Hạn: ' . self::when($t->due_at));
            $t->update(['reminded_at' => now()]); $r++;
        }
        foreach (Task::open()->whereNotNull('due_at')->whereNull('overdue_notified_at')->where('due_at', '<=', now())->with('employee')->get() as $t) {
            self::notifyAssignee($t, 'Quá hạn', 'Hạn đã qua: ' . self::when($t->due_at) . '. Báo người giao nếu cần lùi hạn.');
            self::notifyAssigner($t, 'Quá hạn', $t->employee->full_name . ' chưa xong, hạn ' . self::when($t->due_at));
            $t->update(['overdue_notified_at' => now()]); $o++;
        }
        return ['reminded' => $r, 'overdue' => $o];
    }

    /** open work of one employee, most urgent first */
    public static function mine(Employee $e)
    {
        return Task::with(['assignerEmployee', 'assignerUser'])->where('employee_id', $e->id)->open()
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->get();
    }
}
