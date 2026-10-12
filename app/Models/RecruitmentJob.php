<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecruitmentJob extends Model
{
    protected $guarded = [];
    protected $casts = ['deadline' => 'date'];
    public const STATUS = ['draft' => 'Nháp', 'open' => 'Đang tuyển', 'paused' => 'Tạm dừng', 'closed' => 'Đã dừng tuyển'];
    public const TYPES = ['FULL_TIME' => 'Toàn thời gian', 'PART_TIME' => 'Bán thời gian', 'INTERN' => 'Thực tập', 'CONTRACTOR' => 'Cộng tác viên'];
    public function department() { return $this->belongsTo(Department::class); }
    public function applications() { return $this->hasMany(JobApplication::class, 'job_id'); }
}
