<?php
namespace App\Http\Controllers;

use App\Hr\Hr;
use App\Hr\Knowledge;
use App\Hr\Recruit;
use App\Hr\Tasks;
use App\Models\Task;
use App\Support\YearGoal;
use App\Models\AnnouncementRecipient;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\KbDoc;
use App\Models\KbQuestion;
use App\Models\Order;
use App\Models\RecruitmentJob;
use App\Support\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/** Staff Workplace (/noi-bo). Separate "staff" guard: employees never reach /admin. */
class WorkplaceController extends Controller
{
    private function me(): Employee { return Auth::guard('staff')->user(); }

    // ---------- login ----------

    public function loginForm(Request $r)
    {
        if (Auth::guard('staff')->check()) return redirect('/noi-bo');
        return view('noi-bo.login', ['tab' => $r->query('cach') === 'otp' && Otp::available() ? 'otp' : 'pass', 'otp' => Otp::available(), 'sent' => session('otp_phone')]);
    }

    private function enter(Request $r, Employee $e)
    {
        Auth::guard('staff')->login($e, true);
        $r->session()->regenerate();
        $e->forceFill(['last_login_at' => now(), 'last_login_ip' => $r->ip()])->saveQuietly();
        return redirect()->intended('/noi-bo');
    }

    private function find(string $phone): ?Employee
    {
        $p = Customer::normalizePhone($phone);
        $e = $p ? Employee::where('phone', $p)->first() : null;
        return $e && $e->canUseWorkplace() ? $e : null;
    }

    public function login(Request $r)
    {
        $in = $r->validate(['phone' => 'required|string|max:20', 'password' => 'required|string|max:100']);
        $key = 'wp-login:' . $r->ip() . ':' . preg_replace('/\D/', '', $in['phone']);
        if (RateLimiter::tooManyAttempts($key, 5)) return back()->withInput()->withErrors(['phone' => 'Sai nhiều lần. Thử lại sau ' . ceil(RateLimiter::availableIn($key) / 60) . ' phút.']);
        $e = $this->find($in['phone']);
        if (! $e || ! $e->password || ! Hash::check($in['password'], $e->password)) {
            RateLimiter::hit($key, 900);
            return back()->withInput()->withErrors(['phone' => 'Số điện thoại hoặc mật khẩu không đúng, hoặc tài khoản chưa được mở.']);
        }
        RateLimiter::clear($key);
        return $this->enter($r, $e);
    }

    public function otpSend(Request $r)
    {
        $in = $r->validate(['phone' => 'required|string|max:20']);
        $e = $this->find($in['phone']);
        // same answer whether or not the number exists
        if ($e && ($err = Otp::send($e->phone))) return back()->withInput()->withErrors(['phone' => $err]);
        return redirect('/noi-bo/dang-nhap?cach=otp')->with('otp_phone', Customer::normalizePhone($in['phone']));
    }

    public function otpVerify(Request $r)
    {
        $in = $r->validate(['phone' => 'required|string|max:20', 'code' => 'required|digits:6']);
        $e = $this->find($in['phone']);
        if (! $e || ! Otp::check($e->phone, $in['code'])) return redirect('/noi-bo/dang-nhap?cach=otp')->with('otp_phone', $in['phone'])->withErrors(['code' => 'Mã không đúng hoặc đã hết hạn.']);
        return $this->enter($r, $e);
    }

    public function logout(Request $r)
    {
        Auth::guard('staff')->logout();
        $r->session()->regenerateToken();
        return redirect('/noi-bo/dang-nhap');
    }

    // ---------- pages ----------

    public function home()
    {
        $items = AnnouncementRecipient::with('announcement')->where('employee_id', $this->me()->id)->latest('id')->take(50)->get()
            ->sortByDesc(fn ($r) => [(int) $r->announcement->pinned, $r->id])->values();
        return view('noi-bo.home', ['items' => $items, 'goal' => YearGoal::data($this->me()), 'tasks' => Tasks::mine($this->me())]);
    }

    public function markRead(AnnouncementRecipient $recipient)
    {
        abort_unless($recipient->employee_id === $this->me()->id, 404);
        $recipient->read_at ??= now(); $recipient->save();
        return back();
    }

    public function docs(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $docs = KbDoc::with('department')->visibleTo($this->me())->orderBy('category')->orderBy('sort_order')->orderBy('title')->get();
        if ($q !== '') {
            $w = Knowledge::words($q);
            $docs = $docs->filter(fn ($d) => collect($w)->every(fn ($x) => str_contains(strtolower(\Illuminate\Support\Str::ascii($d->title . ' ' . $d->category . ' ' . $d->text())), $x)))->values();
        }
        return view('noi-bo.docs', ['docs' => $docs->groupBy(fn ($d) => $d->category ?: 'Chung'), 'q' => $q]);
    }

    private function doc(int $id): KbDoc { return KbDoc::with(['department', 'owner'])->visibleTo($this->me())->findOrFail($id); }
    public function docShow(int $doc) { return view('noi-bo.doc', ['d' => $this->doc($doc)]); }

    public function docFile(int $doc)
    {
        $d = $this->doc($doc);
        abort_unless($d->file_path && Storage::disk('local')->exists($d->file_path), 404);
        return Storage::disk('local')->download($d->file_path, $d->file_name);
    }

    public function ask()
    {
        $hist = KbQuestion::where('employee_id', $this->me()->id)->latest('id')->take(20)->get()->reverse()->values();
        $docs = KbDoc::whereIn('id', $hist->pluck('doc_ids')->flatten()->filter()->unique())->pluck('title', 'id');
        return view('noi-bo.ask', ['hist' => $hist, 'docs' => $docs]);
    }

    public function askPost(Request $r)
    {
        $in = $r->validate(['q' => 'required|string|min:3|max:1000']);
        if (RateLimiter::tooManyAttempts('wp-ask:' . $this->me()->id, 30)) return back()->withErrors(['q' => 'Bạn hỏi nhanh quá, thử lại sau ít phút.']);
        RateLimiter::hit('wp-ask:' . $this->me()->id, 600);
        Knowledge::ask($this->me(), $in['q']);
        return redirect('/noi-bo/hoi-ai#cuoi');
    }

    public function rate(Request $r, KbQuestion $question)
    {
        abort_unless($question->employee_id === $this->me()->id, 404);
        $question->update(['helpful' => $r->boolean('ok')]);
        return back();
    }

    // ---------- tasks ----------

    public function tasks()
    {
        $me = $this->me(); $team = Tasks::assignable($me);
        return view('noi-bo.tasks', ['open' => Tasks::mine($me), 'done' => Task::where('employee_id', $me->id)->where('status', 'done')->where('done_at', '>=', now()->subDays(14))->latest('done_at')->get(),
            'team' => $team, 'given' => $team->isEmpty() ? collect() : Task::with('employee')->where('assigned_by_employee_id', $me->id)->where(fn ($w) => $w->open()->orWhere('done_at', '>=', now()->subDays(7)))->orderByRaw("status IN ('done','cancelled')")->orderBy('due_at')->get()]);
    }

    private function myTask(Task $task): Task { abort_unless($task->employee_id === $this->me()->id || $task->assigned_by_employee_id === $this->me()->id, 404); return $task; }

    public function taskShow(Task $task) { return view('noi-bo.task', ['t' => $this->myTask($task)->load(['updates.employee', 'updates.user', 'employee']), 'isMine' => $task->employee_id === $this->me()->id]); }

    public function taskStatus(Request $r, Task $task)
    {
        $t = $this->myTask($task);
        $in = $r->validate(['status' => 'required|in:todo,doing,done,cancelled', 'note' => 'nullable|string|max:1000']);
        // the assignee moves their own work; only the person who gave it may cancel it
        abort_if($in['status'] === 'cancelled' ? $t->assigned_by_employee_id !== $this->me()->id : $t->employee_id !== $this->me()->id, 403);
        Tasks::setStatus($t, $in['status'], $in['note'] ?? null, $this->me());
        return back()->with('ok', 'Đã cập nhật: ' . Task::STATUS[$in['status']]);
    }

    public function taskComment(Request $r, Task $task)
    {
        $t = $this->myTask($task);
        $in = $r->validate(['body' => 'required|string|max:2000']);
        Tasks::log($t, 'comment', $in['body'], $this->me());
        return back();
    }

    public function taskGive(Request $r)
    {
        $team = Tasks::assignable($this->me());
        abort_if($team->isEmpty(), 403);
        $in = $r->validate(['employee_ids' => 'required|array|min:1', 'employee_ids.*' => 'integer|in:' . $team->pluck('id')->implode(','), 'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:3000', 'due_at' => 'nullable|date_format:Y-m-d\TH:i', 'priority' => 'required|in:' . implode(',', array_keys(Task::PRIORITY)),
            'remind_before_hours' => 'required|in:' . implode(',', array_keys(Task::REMIND))], ['employee_ids.required' => 'Chọn người nhận việc.']);
        $in['due_at'] = ! empty($in['due_at']) ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d\TH:i', $in['due_at'], Tasks::TZ)->tz('UTC') : null;
        $n = count(Tasks::assign($in['employee_ids'], $in, null, $this->me()));
        return back()->with('ok', "Đã giao việc cho $n người.");
    }

    public function directory(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $list = Employee::with('department')->where('status', 'active')->whereNotIn('id', Hr::demoIds())->where(fn ($w) => $w->whereNull('onboarding_status')->orWhere('onboarding_status', '!=', 'pending'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('full_name', 'like', "%$q%")->orWhere('job_title', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")))
            ->orderBy('department_id')->orderBy('full_name')->get();
        return view('noi-bo.directory', ['groups' => $list->groupBy(fn ($e) => $e->department?->name ?? 'Chưa xếp phòng'), 'q' => $q]);
    }

    public function jobs() { return view('noi-bo.jobs', ['jobs' => RecruitmentJob::where('status', 'open')->orderBy('sort_order')->get(),
        'mine' => JobApplication::where('referred_by_employee_id', $this->me()->id)->latest('id')->take(20)->get()]); }

    public function refer(Request $r)
    {
        $in = $r->validate(['job_id' => 'required|exists:recruitment_jobs,id', 'name' => 'required|string|max:128', 'phone' => ['required', 'regex:/^0\d{9}$/'],
            'email' => 'nullable|email|max:255', 'message' => 'nullable|string|max:2000'], ['phone.regex' => 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.']);
        $job = RecruitmentJob::findOrFail($in['job_id']);
        $a = JobApplication::create(['job_id' => $job->id, 'position' => $job->title, 'name' => $in['name'], 'phone' => $in['phone'], 'email' => $in['email'] ?? null,
            'message' => $in['message'] ?? null, 'source' => 'referral', 'referred_by_employee_id' => $this->me()->id]);
        Recruit::received($a->fresh('referrer'));
        return back()->with('ok', 'Đã gửi giới thiệu ' . $in['name'] . ' (mã ' . $a->fresh()->code . '). Cảm ơn bạn!');
    }

    public function profile()
    {
        $e = $this->me()->load(['department', 'manager', 'ambassador']);
        $sales = null;
        if ($e->ambassador_id) {
            $m = Order::where('ambassador_id', $e->ambassador_id)->whereNotIn('status', ['cancelled', 'returned'])->where('created_at', '>=', now()->startOfMonth());
            $sales = ['orders' => (clone $m)->count(), 'revenue' => (int) (clone $m)->sum('total'),
                'commission' => (int) Commission::where('ambassador_id', $e->ambassador_id)->whereIn('status', ['pending', 'approved'])->sum('amount')];
        }
        return view('noi-bo.me', ['e' => $e, 'sales' => $sales, 'contact' => Hr::open('contact'), 'private' => Hr::open('private'), 'insurance' => Hr::open('insurance')]);
    }

    /** staff keep their own contact details current; role, status, department are never changed here */
    public function profileSave(Request $r)
    {
        $e = $this->me();
        $in = $r->validate(['email' => 'nullable|email|max:255', 'zalo' => 'nullable|string|max:64', 'personal_phone' => 'nullable|string|max:20', 'telegram' => 'nullable|string|max:64',
            'social_insurance_no' => 'nullable|string|max:20', 'health_insurance_no' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:300', 'birthday' => 'nullable|date_format:Y-m-d', 'emergency_name' => 'nullable|string|max:100', 'emergency_phone' => 'nullable|string|max:20',
            'emergency_relation' => 'nullable|string|max:50', 'bank_account' => 'nullable|string|max:32', 'bank_name' => 'nullable|string|max:100', 'bank_holder' => 'nullable|string|max:100']);
        if (Hr::open('contact')) {
            $e->email = $in['email'] ?? $e->email;
            $e->profile = array_merge($e->profile ?? [], array_intersect_key($in, array_flip(['zalo', 'personal_phone', 'telegram'])));
        }
        if (Hr::open('insurance')) $e->profile = array_merge($e->profile ?? [], array_intersect_key($in, array_flip(['social_insurance_no', 'health_insurance_no'])));
        if (Hr::open('private')) $e->private = array_merge($e->private ?? [], array_intersect_key($in, array_flip(['address', 'birthday', 'emergency_name', 'emergency_phone', 'emergency_relation', 'bank_account', 'bank_name', 'bank_holder'])));
        $e->save();
        return back()->with('ok', 'Đã lưu thông tin.');
    }

    public function password(Request $r)
    {
        $e = $this->me();
        $in = $r->validate(['current' => $e->must_change_password ? 'nullable' : 'required', 'password' => 'required|string|min:8|max:100|confirmed'],
            ['password.min' => 'Mật khẩu mới ít nhất 8 ký tự.', 'password.confirmed' => 'Nhập lại mật khẩu chưa khớp.']);
        if (! $e->must_change_password && ! Hash::check((string) $in['current'], (string) $e->password)) return back()->withErrors(['current' => 'Mật khẩu hiện tại không đúng.']);
        $e->forceFill(['password' => $in['password'], 'must_change_password' => false])->save();
        return redirect('/noi-bo')->with('ok', 'Đã đổi mật khẩu.');
    }
}
