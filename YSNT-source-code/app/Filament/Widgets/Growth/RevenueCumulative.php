<?php
namespace App\Filament\Widgets\Growth;

use App\Support\Growth;
use App\Support\Targets;

class RevenueCumulative extends GrowthChart
{
    protected ?string $heading = 'Doanh thu lũy kế từ 0, so với chỉ tiêu';
    protected bool $money = true;
    protected int|string|array $columnSpan = 'full';

    protected function getType(): string { return 'line'; }

    protected function getData(): array
    {
        $sets = [];
        foreach (Growth::revenue() as $g => $s) $sets[] = ['label' => Targets::GROUPS[$g], 'data' => Growth::cumulative($s)];
        $sets[] = ['label' => 'Tổng', 'data' => Growth::cumulative(array_map('array_sum', array_map(null, ...array_values(Growth::revenue())))), 'borderWidth' => 3];
        $t = Growth::targets();
        if (array_sum($t)) $sets[] = ['label' => 'Chỉ tiêu lũy kế', 'data' => Growth::cumulative($t), 'borderDash' => [6, 4], 'pointRadius' => 0, 'neutral' => true];
        return ['labels' => $this->labels(), 'datasets' => $sets];
    }
}
