<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Announcement extends Model
{
    protected $guarded = [];
    protected $casts = ['target_department_ids' => 'array', 'target_employee_ids' => 'array', 'channels' => 'array', 'pinned' => 'boolean'];
    public const CHANNELS = ['workplace' => 'Workplace', 'email' => 'Email', 'zalo' => 'Zalo OA'];
    public const AUDIENCE = ['all' => 'Tất cả nhân viên đang làm', 'departments' => 'Theo phòng ban', 'employees' => 'Chọn từng người'];
    public function recipients() { return $this->hasMany(AnnouncementRecipient::class); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
