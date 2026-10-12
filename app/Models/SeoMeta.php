<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SeoMeta extends Model
{
    protected $table = 'seo_meta';
    protected $guarded = [];
    protected $casts = ['noindex' => 'boolean'];
    public const TYPES = ['page' => 'Trang', 'product' => 'Sản phẩm', 'category' => 'Danh mục'];

    /** override for one page, cached per request */
    public static function for(string $type, string $key): ?self
    {
        static $memo = [];
        return $memo["$type:$key"] ??= static::where('page_type', $type)->where('page_key', $key)->first();
    }
}
