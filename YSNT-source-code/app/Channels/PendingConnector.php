<?php
namespace App\Channels;

use App\Models\ChannelListing;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;

/** Placeholder until the company's partner app is approved on the platform; keeps the pipeline testable. */
class PendingConnector implements Connector
{
    public function __construct(private string $platform) {}
    public function fetchOrders(SalesChannel $c, Carbon $since): array { throw new NotConfigured("Chưa có ứng dụng được sàn {$this->platform} duyệt. Nhập app key, app secret rồi bấm Cấp quyền gian hàng."); }
    public function pushStock(SalesChannel $c, ChannelListing $l, int $qty): void { throw new NotConfigured("Chưa có ứng dụng được sàn {$this->platform} duyệt."); }
}
