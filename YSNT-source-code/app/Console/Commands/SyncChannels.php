<?php
namespace App\Console\Commands;
use App\Jobs\SyncChannel;
use App\Models\SalesChannel;
use Illuminate\Console\Command;
class SyncChannels extends Command
{
    protected $signature = 'channels:sync';
    protected $description = 'Queue a sync job for every active marketplace channel';
    public function handle(): int
    {
        $n = 0;
        foreach (SalesChannel::whereIn('status', ['active', 'error'])->get() as $c) { SyncChannel::dispatch($c->id); $n++; }
        $this->info("queued $n channel(s)");
        return self::SUCCESS;
    }
}
