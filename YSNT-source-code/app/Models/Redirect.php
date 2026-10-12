<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
class Redirect extends Model
{
    protected $guarded = [];
    protected $casts = ['last_hit_at' => 'datetime'];
    protected static function booted(): void { static::saved(fn () => Cache::forget('redirect_map')); static::deleted(fn () => Cache::forget('redirect_map')); }
    public static function normalize(string $path): string { $p = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/'); return $p === '/' ? $p : rtrim($p, '/'); }
    /** @return array<string,array{0:string,1:int,2:int}> path => [target, code, id] */
    public static function map(): array { return Cache::rememberForever('redirect_map', fn () => static::get(['id', 'from_path', 'to_path', 'code'])->mapWithKeys(fn ($r) => [$r->from_path => [$r->to_path, (int) $r->code, $r->id]])->all()); }
}
