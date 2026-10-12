<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayoutRequest extends Model
{
    protected $guarded = [];
    protected $casts = ["paid_at" => "datetime"];
    public function ambassador() { return $this->belongsTo(Ambassador::class); }
}
