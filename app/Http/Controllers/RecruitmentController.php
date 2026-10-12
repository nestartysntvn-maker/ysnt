<?php
namespace App\Http\Controllers;

use App\Hr\Hr;
use App\Hr\Recruit;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\OnboardingToken;
use App\Models\RecruitmentJob;
use App\Support\Company;
use App\Support\Seo;
use Illuminate\Http\Request;

/** Public careers page (/tuyen-dung) and the new-hire profile portal (/ho-so-nhan-vien?token=...). */
class RecruitmentController extends Controller
{
    public function index()
    {
        $jobs = RecruitmentJob::with('department')->whereIn('status', ['open', 'closed'])->orderByRaw("status = 'open' desc")->orderBy('sort_order')->get();
        $ld = $jobs->where('status', 'open')->map(fn ($j) => array_filter(['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $j->title,
            'description' => nl2br(e(trim($j->description . "\n\nYêu cầu:\n" . $j->requirements . ($j->benefits ? "\n\nQuyền lợi:\n" . $j->benefits : '')))),
            'datePosted' => $j->created_at->toDateString(), 'validThrough' => $j->deadline?->toDateString(), 'employmentType' => $j->employment_type,
            'hiringOrganization' => ['@type' => 'Organization', 'name' => Company::get('company_name'), 'sameAs' => url('/'), 'logo' => Company::logoUrl()],
            'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'streetAddress' => Company::get('showroom_address'), 'addressLocality' => $j->location, 'addressRegion' => 'Khánh Hòa', 'addressCountry' => 'VN']]]))->values()->all();
        return view('recruitment', Seo::apply(['jobs' => $jobs, 'intro' => AppSetting::get('recruit_intro'), 'jsonLd' => $ld,
            'title' => 'Tuyển dụng · ' . Company::get('brand_name'), 'canonical' => url('/tuyen-dung'),
            'desc' => 'Việc làm tại ' . Company::get('company_name') . ': ' . $jobs->where('status', 'open')->pluck('title')->take(5)->implode(', ')], 'page', '/tuyen-dung'));
    }

    public function apply(Request $r)
    {
        if ($r->filled('website')) return redirect('/tuyen-dung')->with('ok', 'Đã nhận hồ sơ.');   // honeypot
        $in = $r->validate(['job_id' => 'nullable|integer', 'position' => 'nullable|string|max:128', 'name' => 'required|string|min:2|max:128',
            'phone' => ['required', 'regex:/^0\d{9}$/'], 'email' => 'required|email|max:255', 'experience' => 'nullable|in:' . implode(',', array_keys(JobApplication::EXPERIENCE)),
            'cv' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png', 'cv_url' => 'nullable|url|max:500', 'message' => 'required|string|min:10|max:3000'],
            ['phone.regex' => 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.', 'message.min' => 'Giới thiệu ngắn về bạn, ít nhất 10 ký tự.', 'cv.max' => 'CV tối đa 10MB.', 'cv.mimes' => 'CV dạng PDF, Word hoặc ảnh.']);
        $job = ! empty($in['job_id']) ? RecruitmentJob::where('status', 'open')->find($in['job_id']) : null;
        $a = JobApplication::create(['job_id' => $job?->id, 'position' => $job?->title ?? ($in['position'] ?: 'Khác'), 'name' => $in['name'], 'phone' => $in['phone'], 'email' => $in['email'],
            'experience' => $in['experience'] ?? null, 'cv_url' => $in['cv_url'] ?? null, 'message' => $in['message'], 'source' => 'web',
            'cv_path' => $r->hasFile('cv') ? $r->file('cv')->store('cv', 'local') : null]);
        $a->refresh();
        Recruit::received($a);
        return redirect('/tuyen-dung#nop')->with('ok', 'Đã nhận hồ sơ của ' . $a->name . '. Mã hồ sơ: ' . $a->code . '. Chúng tôi sẽ liên hệ trong 3 đến 5 ngày làm việc.');
    }

    // ---------- new-hire profile portal ----------

    public function onboardForm(Request $r)
    {
        $t = OnboardingToken::findValid($r->query('token'));
        return view('onboarding', ['t' => $t, 'token' => (string) $r->query('token'), 'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'private' => Hr::open('private'), 'insurance' => Hr::open('insurance'), 'done' => session('done')]);
    }

    public function onboard(Request $r)
    {
        $t = OnboardingToken::findValid($r->input('token'));
        abort_unless($t, 403, 'Đường dẫn đã hết hạn hoặc đã dùng. Liên hệ bộ phận nhân sự để nhận đường dẫn mới.');
        $private = Hr::open('private');
        $rules = ['full_name' => 'required|string|min:2|max:128', 'phone' => ['required', 'regex:/^0\d{9}$/'], 'email' => 'nullable|email|max:255',
            'department_id' => 'required|exists:departments,id', 'job_title' => 'nullable|string|max:128', 'zalo' => 'nullable|string|max:64',
            'social_insurance_no' => 'nullable|string|max:20', 'health_insurance_no' => 'nullable|string|max:20'];
        if ($private) $rules += ['birthday' => 'nullable|date_format:Y-m-d', 'address' => 'nullable|string|max:300', 'id_number' => 'nullable|string|max:20',
            'bank_account' => 'nullable|string|max:32', 'bank_name' => 'nullable|string|max:100', 'bank_holder' => 'nullable|string|max:100',
            'emergency_name' => 'nullable|string|max:100', 'emergency_phone' => 'nullable|string|max:20', 'emergency_relation' => 'nullable|string|max:50',
            'id_front' => 'required|image|mimes:jpg,jpeg,png|max:5120', 'id_back' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'degrees' => 'nullable|array|max:12', 'degrees.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240', 'health' => 'nullable|array|max:6', 'health.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240'];
        $in = $r->validate($rules, ['phone.regex' => 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.', 'id_front.required' => 'Cần ảnh CCCD mặt trước.', 'id_back.required' => 'Cần ảnh CCCD mặt sau.']);
        $phone = Customer::normalizePhone($in['phone']);
        if (Employee::where('phone', $phone)->exists()) return back()->withInput()->withErrors(['phone' => 'Số này đã có hồ sơ. Liên hệ bộ phận nhân sự.']);
        $e = Employee::create(['full_name' => $in['full_name'], 'phone' => $phone, 'email' => $in['email'] ?? $t->application?->email, 'department_id' => $in['department_id'],
            'job_title' => $in['job_title'] ?? $t->application?->position, 'status' => 'active', 'onboarding_status' => 'pending', 'must_change_password' => true,
            'profile' => array_filter(['zalo' => $in['zalo'] ?? null, 'social_insurance_no' => $in['social_insurance_no'] ?? null, 'health_insurance_no' => $in['health_insurance_no'] ?? null])]);
        if ($private) {
            $dir = 'hr/' . $e->id; $files = fn ($k) => collect($r->file($k) ?? [])->map(fn ($f) => $f->store($dir, 'local'))->all();
            $e->private = array_filter(array_intersect_key($in, array_flip(['birthday', 'address', 'id_number', 'bank_account', 'bank_name', 'bank_holder', 'emergency_name', 'emergency_phone', 'emergency_relation'])))
                + ['id_front' => $r->file('id_front')->store($dir, 'local'), 'id_back' => $r->file('id_back')->store($dir, 'local'), 'degrees' => $files('degrees'), 'health' => $files('health')];
            $e->save();
        }
        $t->update(['used_at' => now(), 'employee_id' => $e->id]);
        $t->application?->update(['employee_id' => $e->id]);
        return redirect('/ho-so-nhan-vien')->with('done', $e->fresh()->code);
    }
}
