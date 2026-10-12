<?php
namespace App\Http\Middleware;

use App\Models\AnnouncementRecipient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/** staff guard only; a leaver is signed out on the next click; first login must set a new password */
class WorkplaceGate
{
    public function handle(Request $request, Closure $next)
    {
        $g = Auth::guard('staff');
        if (! $g->check()) { session()->put('url.intended', $request->fullUrl()); return redirect('/noi-bo/dang-nhap'); }
        $e = $g->user();
        if (! $e->canUseWorkplace()) { $g->logout(); return redirect('/noi-bo/dang-nhap')->withErrors(['phone' => 'Tài khoản đã ngưng hoạt động.']); }
        if ($e->must_change_password && ! $request->is('noi-bo/toi', 'noi-bo/doi-mat-khau', 'noi-bo/dang-xuat')) {
            return redirect('/noi-bo/toi')->with('ok', 'Lần đầu đăng nhập: bạn đặt mật khẩu mới giúp mình nhé.');
        }
        View::share(['wpMe' => $e, 'wpUnread' => AnnouncementRecipient::where('employee_id', $e->id)->whereNull('read_at')->count(),
            'wpTasks' => \App\Models\Task::where('employee_id', $e->id)->open()->count()]);
        return $next($request);
    }
}
