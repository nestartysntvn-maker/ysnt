<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerIdentity extends Model
{
    protected $guarded = [];
    public const PROVIDERS = ['web' => 'Web', 'zalo' => 'Zalo', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'shopee' => 'Shopee', 'lazada' => 'Lazada'];
    public function customer() { return $this->belongsTo(Customer::class); }

    /** Find or create the identity; attach it to the customer with the same phone when we learn one. */
    public static function resolve(string $provider, string $externalId, ?string $name = null, ?string $phone = null): self
    {
        $id = static::firstOrNew(['provider' => $provider, 'external_id' => $externalId]);
        if ($name && ! $id->display_name) $id->display_name = mb_substr($name, 0, 190);
        $phone = Customer::normalizePhone($phone);
        if ($phone) {
            $id->phone = $phone;
            if (! $id->customer_id) $id->customer_id = Customer::firstOrCreate(['phone' => $phone], ['name' => $name ?: 'Khách ' . (self::PROVIDERS[$provider] ?? $provider)])->id;
        }
        $id->save();
        return $id;
    }
}
