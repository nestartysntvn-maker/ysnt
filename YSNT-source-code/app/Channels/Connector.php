<?php
namespace App\Channels;

use App\Models\ChannelListing;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;

/** Connector contract every marketplace implements. Orders are returned in one normalized shape. */
interface Connector
{
    /** @return array<int,array{id:string,created_at:string,status:string,buyer_id?:?string,buyer_name:?string,buyer_phone:?string,address:?string,note:?string,discount:int,shipping_fee:int,items:array<int,array{sku_id:?string,sku:?string,name:string,variant:?string,qty:int,unit_price:int}>}> */
    public function fetchOrders(SalesChannel $c, Carbon $since): array;
    public function pushStock(SalesChannel $c, ChannelListing $l, int $qty): void;
}
