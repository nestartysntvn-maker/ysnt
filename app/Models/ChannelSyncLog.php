<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChannelSyncLog extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ["created_at" => "datetime"];
    public function channel() { return $this->belongsTo(SalesChannel::class, "sales_channel_id"); }
}
