<?php
namespace App\Filament\Widgets;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Hr\Hr;
use App\Models\Announcement;
use Filament\Widgets\Widget;

class CompanyNotices extends Widget
{
    protected static ?int $sort = 4;
    protected string $view = 'filament.widgets.notices';
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public function notices()
    {
        return Announcement::with('sender')->withCount(['recipients as read_count' => fn ($q) => $q->whereNotNull('read_at')])
            ->where('audience', '!=', 'employees')->orderByDesc('pinned')->latest('id')->take(5)->get();
    }

    public function canSend(): bool { return Hr::can('announce'); }
    public function createUrl(): string { return AnnouncementResource::getUrl('create'); }
    public function listUrl(): string { return AnnouncementResource::getUrl(); }
}
