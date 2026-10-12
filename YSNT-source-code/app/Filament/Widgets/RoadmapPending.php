<?php
namespace App\Filament\Widgets;

use App\Filament\Pages\IntegrationStatus;
use App\Filament\Pages\RoadmapPage;
use App\Support\Roadmap;
use Filament\Widgets\Widget;

/** plan items not live yet + connections still missing ("hướng chưa triển khai") */
class RoadmapPending extends Widget
{
    protected static ?int $sort = 5;
    protected string $view = 'filament.widgets.roadmap';
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool { return in_array(auth()->user()?->role, ['admin', 'accountant'], true); }

    public function data(): array
    {
        $items = collect(Roadmap::all());
        $open = $items->where('status', '!=', 'xong');
        $missing = collect(IntegrationStatus::groups())->flatten(1)->where('status', '!=', 'done');
        // late first, then by promised quarter; items without a quarter go last
        $key = fn ($i) => ($i['late'] ? 0 : 100000) + ($i['target'] ? (int) substr($i['target'], 3) * 10 + (int) substr($i['target'], 1, 1) : 99999);
        return ['open' => $open->sortBy($key)->values(),
            'counts' => collect(Roadmap::STATUS)->map(fn ($l, $s) => $items->where('status', $s)->count()), 'late' => $open->where('late', true)->count(),
            'missing' => $missing, 'roadmapUrl' => RoadmapPage::getUrl(), 'statusUrl' => IntegrationStatus::getUrl()];
    }
}
