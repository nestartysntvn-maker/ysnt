<?php
namespace App\Hr;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Employee;
use App\Support\Mailer;
use App\Zalo\Oa;
use Illuminate\Support\Collection;

/** Internal announcements: one row per recipient, so every person's email / Zalo result and read time is tracked. */
class Broadcast
{
    /** @return Collection<int,Employee> active employees matching the audience */
    public static function audience(string $audience, array $deptIds = [], array $empIds = []): Collection
    {
        $q = Employee::where('status', 'active')->where(fn ($w) => $w->whereNull('onboarding_status')->orWhere('onboarding_status', '!=', 'pending'));
        if ($audience !== 'employees') $q->whereNotIn('id', Hr::demoIds());
        if ($audience === 'departments') $q->whereIn('department_id', $deptIds ?: [0]);
        if ($audience === 'employees') $q->whereIn('id', $empIds ?: [0]);
        return $q->get();
    }

    public static function send(Announcement $a): Announcement
    {
        $people = self::audience($a->audience, $a->target_department_ids ?? [], $a->target_employee_ids ?? []);
        $ch = $a->channels ?? ['workplace'];
        $html = view('emails.simple', ['lines' => [$a->title, $a->body], 'button' => ['Mở Workplace', url('/noi-bo')]])->render();
        $emailOk = 0; $zaloOk = 0;
        foreach ($people as $e) {
            $r = AnnouncementRecipient::firstOrCreate(['announcement_id' => $a->id, 'employee_id' => $e->id]);
            if (in_array('email', $ch, true)) {
                $st = $e->email ? Mailer::send($e->email, '[Nội bộ] ' . $a->title, $html, 'announcement', 'TB-' . $a->id) : 'skipped';
                $r->email_status = $st; $emailOk += $st === 'ok';
            }
            if (in_array('zalo', $ch, true)) {
                [$st] = $e->zaloId() ? Oa::sendText($e->zaloId(), "[Thông báo nội bộ] {$a->title}\n{$a->body}" . ($a->link ? "\n{$a->link}" : '')) : ['skipped'];
                $r->zalo_status = $st; $zaloOk += $st === 'sent';
            }
            $r->save();
        }
        $a->update(['recipient_count' => $people->count(), 'email_ok' => $emailOk, 'zalo_ok' => $zaloOk]);
        return $a;
    }
}
