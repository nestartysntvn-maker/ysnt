<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConversationMessage extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['attachments' => 'array'];
    public const STATUS = ['received' => 'Đã nhận', 'sent' => 'Đã gửi', 'dry_run' => 'Chạy thử', 'failed' => 'Lỗi', 'draft' => 'Nháp'];
    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function user() { return $this->belongsTo(User::class); }
}
