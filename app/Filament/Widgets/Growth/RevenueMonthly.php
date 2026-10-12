<?php
namespace App\Filament\Widgets\Growth;

use App\Support\Growth;
use App\Support\Targets;

class RevenueMonthly extends GrowthChart
{
    protected ?string $heading = 'Doanh thu từng tháng theo nhóm kênh';
    protected bool $money = true;
    protected bool $stacked = true;

    protected function getType(): string { return 'bar'; }

    protected function getData(): array
    {
        $sets = [];
        foreach (Growth::revenue() as $g => $s) $sets[] = ['label' => Targets::GROUPS[$g], 'data' => $s, 'stack' => 'r', 'borderWidth' => 2, 'borderColor' => 'rgba(0,0,0,0)'];
        return ['labels' => $this->labels(), 'datasets' => $sets];
    }
}
