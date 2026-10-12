<?php
namespace App\Filament\Resources\Announcements\Pages;
use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
class ViewAnnouncement extends Page
{
    use InteractsWithRecord;
    protected static string $resource = AnnouncementResource::class;
    protected string $view = 'filament.pages.announcement';
    public static function canAccess(array $parameters = []): bool { return AnnouncementResource::canViewAny(); }
    public function mount(int|string $record): void { $this->record = $this->resolveRecord($record); }
    public function getTitle(): string { return $this->record->title; }
}
