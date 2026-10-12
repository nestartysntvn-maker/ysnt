<?php
namespace App\Jobs;
use App\Channels\ChannelSync;
use App\Models\SalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SyncChannel implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $tries = 1; public int $timeout = 300;
    public function __construct(public int $channelId) {}
    public function uniqueId(): string { return 'channel-' . $this->channelId; }
    public function handle(): void
    {
        $c = SalesChannel::find($this->channelId); if (! $c) return;
        ChannelSync::refreshToken($c);
        if ($c->sync_orders) ChannelSync::syncOrders($c);
        if ($c->sync_stock) ChannelSync::syncStock($c);
    }
}
