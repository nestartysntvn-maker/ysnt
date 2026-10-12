<?php
namespace App\Filament\Resources\Conversations\Pages;

use App\Ai\Assist;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Inbox\Inbox;
use App\Models\Conversation;
use App\Models\Customer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewConversation extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ConversationResource::class;
    protected string $view = 'filament.pages.conversation';
    public string $reply = '';
    public string $phone = '';

    public static function canAccess(array $parameters = []): bool { return ConversationResource::canViewAny(); }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->update(['unread' => 0]);
    }

    public function getTitle(): string { return ($this->record->name ?: 'Hội thoại') . ' · ' . (Conversation::CHANNELS[$this->record->channel] ?? $this->record->channel); }

    public function conv(): Conversation { return $this->record->fresh(['messages.user', 'customer', 'identity', 'assignee']); }

    public function send(): void
    {
        $text = trim($this->reply);
        if ($text === '') return;
        $m = Inbox::send($this->record, mb_substr($text, 0, 2000), 'staff');
        $this->record->update(['needs_human' => false]);
        $this->reply = '';
        if ($m->status === 'failed') Notification::make()->title('Chưa gửi được')->body($m->error)->danger()->send();
        elseif ($m->status === 'dry_run') Notification::make()->title('Đã lưu (chạy thử)')->body($m->error)->info()->send();
    }

    public function suggest(): void
    {
        $r = Assist::suggestReply($this->record);
        if ($r['status'] === 'ok') { $this->reply = $r['text']; return; }
        Notification::make()->title($r['status'] === 'dry_run' ? 'Chưa kết nối AI hỗ trợ nhân viên' : 'AI chưa gợi ý được')->body($r['error'])->warning()->send();
    }

    public function toggleAi(): void { $this->record->update(['ai_enabled' => ! $this->record->ai_enabled]); }
    public function handBack(): void { $this->record->update(['needs_human' => false]); }
    public function close(): void { $this->record->update(['status' => $this->record->status === 'open' ? 'closed' : 'open', 'unread' => 0]); }
    public function setTag(string $tag): void { $this->record->update(['tag' => array_key_exists($tag, Conversation::TAGS) ? $tag : null]); }

    /** tie this chat (and every later one from the same Zalo / Facebook / TikTok account) to one customer */
    public function link(): void
    {
        $p = Customer::normalizePhone($this->phone);
        if (! $p) { Notification::make()->title('Số điện thoại gồm 10 chữ số, bắt đầu bằng 0')->danger()->send(); return; }
        $cust = Customer::firstOrCreate(['phone' => $p], ['name' => $this->record->name ?: 'Khách ' . $p]);
        $this->record->identity?->update(['customer_id' => $cust->id, 'phone' => $p]);
        $this->record->update(['customer_id' => $cust->id]);
        $this->phone = '';
        Notification::make()->title('Đã gắn với khách ' . $cust->name . ' · ' . $cust->phone)->success()->send();
    }
}
