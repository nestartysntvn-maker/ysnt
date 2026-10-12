<?php
namespace App\Filament\Widgets;

use App\Hr\Hr;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** everything waiting for someone, across modules; each person sees only what their role can act on */
class TodoList extends Widget
{
    protected static ?int $sort = 3;
    protected string $view = 'filament.widgets.todo';
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public function items(): array
    {
        $role = auth()->user()?->role; $is = fn (string ...$r) => $role === 'admin' || in_array($role, $r, true);
        $has = fn (string $t) => Schema::hasTable($t);
        $rows = [
            [$is('sales'), 'Đơn mới chưa xác nhận', fn () => DB::table('orders')->where('status', 'new')->count(), '/admin/orders'],
            [$is('sales'), 'Khách đang chờ nhân viên trả lời', fn () => DB::table('conversations')->where('status', 'open')->where('needs_human', true)->count(), '/admin/conversations'],
            [$is('sales'), 'Hội thoại có tin chưa đọc', fn () => DB::table('conversations')->where('status', 'open')->where('unread', '>', 0)->count(), '/admin/conversations'],
            [$is('accountant'), 'Hoa hồng chờ duyệt', fn () => DB::table('commissions')->where('status', 'pending')->count(), '/admin/commissions'],
            [$is('accountant'), 'Yêu cầu rút tiền chờ chi', fn () => DB::table('payout_requests')->where('status', 'pending')->count(), '/admin/payout-requests'],
            [$is(), 'Gian hàng sàn đang lỗi kết nối', fn () => DB::table('sales_channels')->where('status', 'error')->count(), '/admin/sales-channels'],
            [Hr::can('recruit') && $has('job_applications'), 'Hồ sơ ứng viên mới chưa xử lý', fn () => DB::table('job_applications')->whereNull('deleted_at')->where('status', 'new')->count(), '/admin/job-applications'],
            [Hr::can('recruit') && $has('job_applications'), 'Lịch phỏng vấn 7 ngày tới', fn () => DB::table('job_applications')->whereNull('deleted_at')->where('status', 'interview')->whereBetween('interview_at', [now(), now()->addDays(7)])->count(), '/admin/job-applications'],
            [Hr::can('staff') && $has('employees'), 'Hồ sơ nhân viên chờ duyệt', fn () => DB::table('employees')->where('onboarding_status', 'pending')->count(), '/admin/employees'],
            [Hr::can('staff') && $has('employees'), 'Nhân viên chưa từng vào Workplace', fn () => DB::table('employees')->where('status', 'active')->whereNull('last_login_at')->whereNotIn('id', Hr::demoIds())->count(), '/admin/employees'],
            [Hr::can('kb') && $has('kb_questions'), 'Câu hỏi AI chưa có trong quy trình (30 ngày)', fn () => DB::table('kb_questions')->where('status', 'unknown')->where('created_at', '>=', now()->subDays(30))->count(), '/admin/kb-questions'],
            [$has('tasks'), 'Việc bạn giao đã quá hạn', fn () => DB::table('tasks')->whereIn('status', ['todo', 'doing'])->where('due_at', '<', now())->where('assigned_by_user_id', auth()->id())->count(), '/admin/tasks'],
            [Hr::can('staff') && $has('tasks'), 'Việc quá hạn toàn công ty', fn () => DB::table('tasks')->whereIn('status', ['todo', 'doing'])->where('due_at', '<', now())->count(), '/admin/tasks'],
            [Hr::can('staff') && $has('tasks'), 'Việc đến hạn trong 24 giờ', fn () => DB::table('tasks')->whereIn('status', ['todo', 'doing'])->whereBetween('due_at', [now(), now()->addDay()])->count(), '/admin/tasks'],
            [$is('content', 'warehouse'), 'Quy cách đang hết hàng', fn () => DB::table('product_variants')->where('is_available', false)->count(), '/admin/products'],
        ];
        $out = [];
        foreach ($rows as [$ok, $label, $count, $url]) if ($ok && ($n = (int) $count()) > 0) $out[] = compact('label', 'n', 'url');
        return $out;
    }
}
