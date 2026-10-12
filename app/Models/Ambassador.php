<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ambassador extends Model {
    protected $guarded = [];
    protected $casts = ['bank_info' => 'encrypted'];
    public function policy() { return CommissionPolicy::where('ambassador_type', $this->type)->first(); }
    public function parent() { return $this->belongsTo(Ambassador::class, 'parent_id'); }
    public function orders() { return $this->hasMany(Order::class); }
    public function commissions() { return $this->hasMany(Commission::class); }
}
