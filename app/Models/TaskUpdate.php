<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TaskUpdate extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function author(): string { return $this->employee?->full_name ?? $this->user?->name ?? 'Hệ thống'; }
}
