<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ["changes" => "array", "created_at" => "datetime"];
    public function user() { return $this->belongsTo(User::class); }
}
