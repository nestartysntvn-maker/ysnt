<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Department extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function employees() { return $this->hasMany(Employee::class); }
    public function head() { return $this->belongsTo(Employee::class, 'head_employee_id'); }
    protected static function booted(): void
    {
        static::saving(fn (Department $d) => $d->name = trim($d->name));
    }
}
