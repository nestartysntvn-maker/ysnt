<?php
namespace App\Jobs;
use App\Models\Order;
use App\Support\Addons;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
/** queued side effects of an order (mail, Meta CAPI); each one logs its own result */
class RunAddon implements ShouldQueue
{
    use Queueable;
    public int $tries = 2; public array $backoff = [120];
    public function __construct(public string $task, public int $orderId, public array $ctx = []) {}
    public function handle(): void { if ($o = Order::find($this->orderId)) Addons::run($this->task, $o, $this->ctx); }
}
