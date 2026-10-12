<?php
namespace App\Jobs;
use App\Ai\Advisor;
use App\Inbox\Inbox;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
/** AI answer for a Zalo / Messenger / Instagram / TikTok message; several quick messages collapse into one reply */
class AiReply implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $tries = 1; public int $timeout = 120; public int $uniqueFor = 60;
    public function __construct(public int $conversationId) { $this->delay(now()->addSeconds(8)); }
    public function uniqueId(): string { return 'ai-reply-' . $this->conversationId; }
    public function handle(): void
    {
        $c = Conversation::find($this->conversationId);
        if (! $c || ! Inbox::aiOn($c) || $c->messages()->latest('id')->value('direction') !== 'in') return;
        Advisor::reply($c);
    }
}
