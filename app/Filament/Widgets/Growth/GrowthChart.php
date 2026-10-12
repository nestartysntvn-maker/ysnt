<?php
namespace App\Filament\Widgets\Growth;

use App\Support\Growth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** shared look: validated categorical palette (light + dark steps), thin marks, recessive grid, one axis */
abstract class GrowthChart extends ChartWidget
{
    public const LIGHT = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];
    public const DARK = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181'];
    protected ?string $maxHeight = '280px';
    protected ?string $pollingInterval = null;   // history does not change by the second
    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];
    /** money axis shows "tỷ / triệu" */
    protected bool $money = false;
    protected bool $stacked = false;
    protected bool $legend = true;   // single-series charts set false: the title names the series

    public static function isDiscovered(): bool { return false; }   // only on the Growth page, not the dashboard

    protected function labels(): array { return array_map([Growth::class, 'label'], Growth::months()); }

    protected function getOptions(): RawJs
    {
        $stack = $this->stacked ? 'true' : 'false'; $legend = $this->legend ? 'true' : 'false';
        $fmt = $this->money ? "v => v >= 1e9 ? (v/1e9).toLocaleString('vi-VN',{maximumFractionDigits:2}) + ' tỷ' : (v/1e6).toLocaleString('vi-VN',{maximumFractionDigits:0}) + ' tr'" : "v => v.toLocaleString('vi-VN')";
        return RawJs::make(<<<JS
        {
            datasets: {
                line: { borderColor: c => c.dataset.neutral ? (document.documentElement.classList.contains('dark') ? '#a1a1aa' : '#71717a') : (document.documentElement.classList.contains('dark') ? {$this->palette(self::DARK)} : {$this->palette(self::LIGHT)})[c.datasetIndex % 5],
                        backgroundColor: c => (document.documentElement.classList.contains('dark') ? {$this->palette(self::DARK)} : {$this->palette(self::LIGHT)})[c.datasetIndex % 5],
                        pointBackgroundColor: c => c.dataset.neutral ? (document.documentElement.classList.contains('dark') ? '#a1a1aa' : '#71717a') : (document.documentElement.classList.contains('dark') ? {$this->palette(self::DARK)} : {$this->palette(self::LIGHT)})[c.datasetIndex % 5],
                        pointBorderColor: c => document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff',
                        borderWidth: 2, pointRadius: 4, pointBorderWidth: 2, pointHoverRadius: 6, tension: 0, fill: false },
                bar: { backgroundColor: c => (document.documentElement.classList.contains('dark') ? {$this->palette(self::DARK)} : {$this->palette(self::LIGHT)})[c.datasetIndex % 5],
                       borderRadius: 4, borderSkipped: 'start', borderWidth: 0, maxBarThickness: 36 },
            },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: {$legend}, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + ({$fmt})(c.parsed.y) } },
            },
            scales: {
                x: { stacked: {$stack}, grid: { display: false } },
                y: { stacked: {$stack}, beginAtZero: true, grid: { color: 'rgba(128,128,128,.15)' }, border: { display: false }, ticks: { precision: 0, callback: {$fmt} } },
            },
        }
        JS);
    }

    /** single-quoted: the options are written into an HTML attribute (x-data="...") */
    private function palette(array $a): string { return "['" . implode("','", $a) . "']"; }
}
