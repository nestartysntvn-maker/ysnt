<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Task extends Model
{
    protected $guarded = [];
    protected $casts = ['due_at' => 'datetime', 'reminded_at' => 'datetime', 'overdue_notified_at' => 'datetime', 'done_at' => 'datetime'];
    public const STATUS = ['todo' => 'Chưa làm', 'doing' => 'Đang làm', 'done' => 'Đã xong', 'cancelled' => 'Đã hủy'];
    public const PRIORITY = ['low' => 'Thấp', 'normal' => 'Bình thường', 'high' => 'Cao', 'urgent' => 'Gấp'];
    public const REMIND = [2 => 'Trước 2 giờ', 4 => 'Trước 4 giờ', 24 => 'Trước 1 ngày', 48 => 'Trước 2 ngày'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function assignerUser() { return $this->belongsTo(User::class, 'assigned_by_user_id'); }
    public function assignerEmployee() { return $this->belongsTo(Employee::class, 'assigned_by_employee_id'); }
    public function updates() { return $this->hasMany(TaskUpdate::class)->orderBy('id'); }
    public function isOpen(): bool { return in_array($this->status, ['todo', 'doing'], true); }
    public function isOverdue(): bool { return $this->isOpen() && $this->due_at && $this->due_at->isPast(); }
    public function assignerName(): string { return $this->assignerEmployee?->full_name ?? $this->assignerUser?->name ?? 'Công ty'; }
    public function scopeOpen($q) { return $q->whereIn('status', ['todo', 'doing']); }
}
