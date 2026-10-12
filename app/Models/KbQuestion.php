<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KbQuestion extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['doc_ids' => 'array', 'helpful' => 'boolean'];
    public const STATUS = ['answered' => 'Đã trả lời', 'unknown' => 'Không có trong tài liệu', 'dry_run' => 'Chưa kết nối AI', 'error' => 'Lỗi'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
