<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZaloMessage extends Model
{
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime'];
    public function template() { return $this->belongsTo(ZaloTemplate::class, 'zalo_template_id'); }
    public function order() { return $this->belongsTo(Order::class); }
}
