<?php
namespace App\Filament\Widgets\Growth;

use App\Support\Growth;

class ConnectionsCumulative extends GrowthChart
{
    protected ?string $heading = 'Kết nối khách lũy kế theo kênh (Zalo, Facebook, Instagram, TikTok, web)';

    protected function getType(): string { return 'line'; }

    protected function getData(): array
    {
        $sets = [];
        foreach (Growth::connections() as $p => $s) $sets[] = ['label' => Growth::PROVIDERS[$p], 'data' => Growth::cumulative($s)];
        return ['labels' => $this->labels(), 'datasets' => $sets];
    }
}
