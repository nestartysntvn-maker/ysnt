<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JobApplication extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['interview_at' => 'datetime'];
    public const STATUS = ['new' => 'Mới', 'interview' => 'Hẹn phỏng vấn', 'hired' => 'Đã nhận', 'rejected' => 'Từ chối'];
    public const EXPERIENCE = ['fresher' => 'Mới ra trường', '1-2y' => '1-2 năm', '3-5y' => '3-5 năm', '5y+' => 'Trên 5 năm'];
    public const SOURCE = ['web' => 'Web', 'manual' => 'Nhân sự nhập', 'referral' => 'Nhân viên giới thiệu'];
    public function job() { return $this->belongsTo(RecruitmentJob::class, 'job_id'); }
    public function referrer() { return $this->belongsTo(Employee::class, 'referred_by_employee_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
    protected static function booted(): void
    {
        static::created(fn (JobApplication $a) => $a->code ? null : $a->forceFill(['code' => 'YS-CV-' . now()->format('ymd') . '-' . str_pad((string) $a->id, 4, '0', STR_PAD_LEFT)])->saveQuietly());
    }
}
