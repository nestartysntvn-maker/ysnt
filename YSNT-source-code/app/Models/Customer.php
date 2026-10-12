<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model
{
    public const GROUPS = ['le' => 'Khách lẻ', 'doanh_nghiep' => 'Khách doanh nghiệp', 'dai_ly' => 'Đại lý', 'quoc_te' => 'Khách quốc tế'];
    public const SOURCES = ['showroom' => 'Showroom', 'web' => 'Web', 'tiktok' => 'TikTok Shop', 'shopee' => 'Shopee', 'hdv' => 'Hướng dẫn viên, tài xế', 'dai_ly' => 'Đại lý', 'hoi_cho' => 'Sự kiện, hội chợ', 'danh_sach_cu' => 'Danh sách cũ'];

    protected $guarded = [];
    protected $casts = ['birthday' => 'date'];
    public function orders() { return $this->hasMany(Order::class); }
    public function identities() { return $this->hasMany(CustomerIdentity::class); }
    public function importBatch() { return $this->belongsTo(CustomerImportBatch::class, 'import_batch_id'); }

    /** 0xxxxxxxxx from "+84 9x", "84 9x", "09x.xxx" ...; null when it is not a usable VN mobile (masked marketplace numbers too) */
    public static function normalizePhone(?string $p): ?string
    {
        $d = preg_replace('/\D/', '', (string) $p);
        if (str_starts_with($d, '84') && strlen($d) === 11) $d = '0' . substr($d, 2);
        return preg_match('/^0\d{9}$/', $d) ? $d : null;
    }
}
