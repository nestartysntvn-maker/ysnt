<?php
namespace App\Http\Controllers;

use App\Hr\Hr;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Storage;

/** private files uploaded on the settings pages (business licence scan...), admin only */
class AdminFileController extends Controller
{
    public const FILES = ['gpkd_file', 'legal_rep_id_file'];

    public function show(string $key)
    {
        if (! auth()->check()) return redirect('/admin/login');
        abort_unless(auth()->user()->is_active && auth()->user()->role === 'admin' && in_array($key, self::FILES, true), 403);
        $path = AppSetting::get($key);
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }

    private function serve(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }

    public function cv(int $application)
    {
        if (! auth()->check()) return redirect('/admin/login');
        abort_unless(auth()->user()->is_active && Hr::can('recruit'), 403);
        return $this->serve(JobApplication::withTrashed()->findOrFail($application)->cv_path);
    }

    /** ID card photos, degrees, health certificates: private data, admin only (as go365) */
    public function hrFile(int $employee, string $kind, ?int $i = null)
    {
        if (! auth()->check()) return redirect('/admin/login');
        abort_unless(auth()->user()->is_active && Hr::can('private'), 403);
        $v = Employee::findOrFail($employee)->priv($kind);
        return $this->serve(is_array($v) ? ($v[(int) $i] ?? null) : $v);
    }
}
