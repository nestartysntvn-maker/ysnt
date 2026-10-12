<?php
namespace App\Filament\Widgets;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
class SalesOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Order::whereDate('created_at', today())->whereNotIn('status', ['cancelled', 'returned']);
        $month = Order::where('created_at', '>=', now()->startOfMonth())->whereNotIn('status', ['cancelled', 'returned']);
        return [
            Stat::make('Đơn hôm nay', (clone $today)->count())->description('Doanh thu ' . OrderResource::vnd((clone $today)->sum('total'))),
            Stat::make('Doanh thu tháng này', OrderResource::vnd((clone $month)->sum('total')))->description((clone $month)->count() . ' đơn'),
            Stat::make('Đơn chờ xử lý', Order::where('status', 'new')->count())->description('Trạng thái "Mới"')->url(OrderResource::getUrl('index')),
            Stat::make('Quy cách hết hàng', ProductVariant::where('is_available', false)->count())->description('trên ' . ProductVariant::count() . ' quy cách'),
        ];
    }
}
