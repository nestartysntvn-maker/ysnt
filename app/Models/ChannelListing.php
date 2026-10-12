<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChannelListing extends Model
{
    protected $guarded = [];
    protected $casts = ["sync_stock" => "boolean", "last_synced_at" => "datetime"];
    public function channel() { return $this->belongsTo(SalesChannel::class, "sales_channel_id"); }
    public function variant() { return $this->belongsTo(ProductVariant::class, "variant_id"); }
}
