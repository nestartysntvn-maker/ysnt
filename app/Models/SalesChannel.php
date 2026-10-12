<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesChannel extends Model
{
    protected $guarded = [];
    protected $casts = ["credentials" => "encrypted:array", "sync_orders" => "boolean", "sync_stock" => "boolean", "token_expires_at" => "datetime", "last_order_sync_at" => "datetime", "last_stock_sync_at" => "datetime"];
    protected $hidden = ["credentials"];
    public function listings() { return $this->hasMany(ChannelListing::class); }
    public function logs() { return $this->hasMany(ChannelSyncLog::class)->latest("id"); }
}
