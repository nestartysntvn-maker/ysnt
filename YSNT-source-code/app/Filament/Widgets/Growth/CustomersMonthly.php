<?php
namespace App\Filament\Widgets\Growth;

use App\Support\Growth;

class CustomersMonthly extends GrowthChart
{
    protected ?string $heading = 'Khách mua lần đầu mỗi tháng';
    protected bool $legend = false;

    protected function getType(): string { return 'bar'; }

    protected function getData(): array { return ['labels' => $this->labels(), 'datasets' => [['label' => 'Khách mới', 'data' => Growth::newBuyers()]]]; }
}
