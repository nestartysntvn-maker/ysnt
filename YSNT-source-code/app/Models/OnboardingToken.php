<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class OnboardingToken extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    public function application() { return $this->belongsTo(JobApplication::class, 'job_application_id'); }

    /** @return string the plain token for the link; only its hash is stored */
    public static function issue(?JobApplication $a, int $days = 14): string
    {
        $plain = Str::random(40);
        static::create(['job_application_id' => $a?->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDays($days)]);
        return $plain;
    }
    public static function findValid(?string $plain): ?self
    {
        if (! $plain || strlen($plain) !== 40) return null;
        return static::where('token_hash', hash('sha256', $plain))->whereNull('used_at')->where('expires_at', '>', now())->first();
    }
}
