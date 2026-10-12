<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockMovement extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ["created_at" => "datetime"];
    public function variant() { return $this->belongsTo(ProductVariant::class, "variant_id"); }
    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
    protected static function booted(): void
    {
        static::creating(function (StockMovement $s) {
            $q = (int) $s->qty;
            if (in_array($s->type, ["in", "return"], true)) $q = abs($q);
            if (in_array($s->type, ["out_damage", "sale"], true)) $q = -abs($q);
            $s->qty = $q;
            $s->user_id = $s->user_id ?? auth()->id();
            $v = ProductVariant::whereKey($s->variant_id)->lockForUpdate()->first();
            $after = (int) ($v->stock_qty ?? 0) + $q;
            $v->update(["stock_qty" => $after]);
            $s->stock_after = $after;
        });
    }
}
