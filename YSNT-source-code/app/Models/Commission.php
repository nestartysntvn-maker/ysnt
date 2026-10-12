<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Commission extends Model
{
    protected $guarded = [];
    protected $casts = ["approved_at" => "datetime", "paid_at" => "datetime"];
    public function order() { return $this->belongsTo(Order::class); }
    public function ambassador() { return $this->belongsTo(Ambassador::class); }
}
