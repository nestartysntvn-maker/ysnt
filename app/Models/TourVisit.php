<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TourVisit extends Model
{
    protected $guarded = [];
    protected $casts = ['visited_at' => 'datetime'];
    public function ambassador() { return $this->belongsTo(Ambassador::class); }
    protected static function booted(): void { static::creating(fn (TourVisit $v) => $v->created_by = $v->created_by ?? auth()->id()); }
}
