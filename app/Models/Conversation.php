<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Conversation extends Model
{
    protected $guarded = [];
    protected $casts = ['needs_human' => 'boolean', 'ai_enabled' => 'boolean', 'last_message_at' => 'datetime'];
    public const CHANNELS = ['web' => 'Chat web', 'zalo' => 'Zalo OA', 'facebook' => 'Messenger', 'instagram' => 'Instagram', 'tiktok' => 'TikTok Shop'];
    public const STATUS = ['open' => 'Đang mở', 'closed' => 'Đã xong'];
    public const TAGS = ['b2c' => 'Khách lẻ', 'b2b' => 'Doanh nghiệp', 'agent' => 'Đại lý', 'complaint' => 'Khiếu nại'];
    public function identity() { return $this->belongsTo(CustomerIdentity::class, 'customer_identity_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages() { return $this->hasMany(ConversationMessage::class)->orderBy('id'); }
}
