<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];
    public function items() { return $this->hasMany(OrderItem::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function ambassador() { return $this->belongsTo(Ambassador::class); }
    public function commissions() { return $this->hasMany(Commission::class); }
    public function statusLogs() { return $this->hasMany(OrderStatusLog::class)->latest('id'); }

    protected static function booted(): void
    {
        static::created(function (Order $o) {
            OrderStatusLog::create(['order_id' => $o->id, 'from_status' => null, 'to_status' => $o->status, 'user_id' => auth()->id(), 'note' => 'Tạo đơn']);
            \Illuminate\Support\Facades\DB::afterCommit(fn () => \App\Zalo\Zns::forOrder('order_confirmed', $o->fresh()));
            \App\Support\Addons::orderCreated($o);
        });
        static::updated(function (Order $o) {
            if (! $o->wasChanged('status')) return;
            OrderStatusLog::create(['order_id' => $o->id, 'from_status' => $o->getOriginal('status'), 'to_status' => $o->status, 'user_id' => auth()->id()]);
            if ($o->status === 'shipping') \Illuminate\Support\Facades\DB::afterCommit(fn () => \App\Zalo\Zns::forOrder('order_shipping', $o->fresh()));
            if (in_array($o->status, ['cancelled', 'returned'], true)) {
                $o->commissions()->whereIn('status', ['pending', 'approved'])->update(['status' => 'cancelled']);
            }
        });
    }
}
