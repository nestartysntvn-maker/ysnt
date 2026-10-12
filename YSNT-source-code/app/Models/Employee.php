<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** One row per staff member (300-400 people). Logs into the Workplace with phone + password (or OTP), never into /admin. */
class Employee extends Authenticatable
{
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token', 'private'];
    public const STATUS = ['active' => 'Đang làm', 'inactive' => 'Nghỉ việc', 'suspended' => 'Tạm khóa'];
    public const TERMINATION = ['resign' => 'Tự nghỉ', 'dismissed' => 'Cho thôi việc'];

    protected function casts(): array
    {
        return ['profile' => 'array', 'private' => 'encrypted:array', 'allowances' => 'array', 'hire_date' => 'date', 'last_working_day' => 'date',
            'last_login_at' => 'datetime', 'terminated_at' => 'datetime', 'password' => 'hashed', 'must_change_password' => 'boolean', 'commission_enabled' => 'boolean'];
    }

    public function department() { return $this->belongsTo(Department::class); }
    public function manager() { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function subordinates() { return $this->hasMany(Employee::class, 'manager_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function ambassador() { return $this->belongsTo(Ambassador::class); }
    public function inbox() { return $this->hasMany(AnnouncementRecipient::class); }

    public function canUseWorkplace(): bool { return $this->status === 'active' && $this->onboarding_status !== 'pending'; }
    public function p(string $k): ?string { $v = ($this->profile ?? [])[$k] ?? null; return is_array($v) ? implode(', ', $v) : $v; }
    public function priv(string $k): mixed { return ($this->private ?? [])[$k] ?? null; }

    /** Zalo OA user id: stored, or found through the customer identity created when this phone shared info with the OA */
    public function zaloId(): ?string
    {
        if ($this->zalo_user_id) return $this->zalo_user_id;
        $id = CustomerIdentity::where('provider', 'zalo')->where('phone', $this->phone)->value('external_id');
        if ($id) $this->forceFill(['zalo_user_id' => $id])->saveQuietly();
        return $id;
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $e) { $e->phone = Customer::normalizePhone($e->phone) ?? $e->phone; });
        static::created(fn (Employee $e) => $e->code ? null : $e->forceFill(['code' => 'NV-' . str_pad((string) $e->id, 4, '0', STR_PAD_LEFT)])->saveQuietly());
        static::updating(function (Employee $e) {
            if ($e->isDirty('status') && $e->status !== 'active' && ! $e->terminated_at) $e->terminated_at = now();
            if ($e->isDirty('status') && $e->status === 'active') $e->terminated_at = null;
        });
    }
}
