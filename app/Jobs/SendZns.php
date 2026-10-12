<?php
namespace App\Jobs;
use App\Models\ZaloMessage;
use App\Zalo\Zns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SendZns implements ShouldQueue
{
    use Queueable;
    public int $tries = 3; public array $backoff = [60, 300];
    public function __construct(public int $messageId) {}
    public function handle(): void { if ($m = ZaloMessage::find($this->messageId)) { if ($m->status === 'queued' || $m->status === 'failed') Zns::deliver($m); } }
}
