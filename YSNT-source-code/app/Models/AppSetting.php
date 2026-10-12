<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class AppSetting extends Model
{
    protected $guarded = [];

    /** raw rows loaded once per request / queued job (scoped binding in AppServiceProvider) */
    private static function memo(): \ArrayObject { return app('app_settings.memo'); }

    public static function get(string $k, $default = null)
    {
        $v = self::memo()[$k] ?? null;
        if ($v === null) return $default;
        try { return Crypt::decryptString($v); } catch (\Throwable) { return $default; }
    }
    public static function put(string $k, ?string $v): void
    {
        $enc = $v === null || $v === '' ? null : Crypt::encryptString($v);
        static::updateOrCreate(['key' => $k], ['value' => $enc]);
        self::memo()[$k] = $enc;
    }
    public static function enabled(string $k): bool { return static::get($k) === '1'; }
}
