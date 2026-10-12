<?php
namespace App\Filament\Resources\Announcements\Pages;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Hr\Broadcast;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;
    protected static ?string $title = 'Soạn thông báo nội bộ';
    protected static bool $canCreateAnother = false;
    protected function getCreateFormAction(): \Filament\Actions\Action { return parent::getCreateFormAction()->label('Gửi')->requiresConfirmation()->modalHeading('Gửi thông báo này?'); }
    protected function mutateFormDataBeforeCreate(array $d): array { $d['sent_by'] = auth()->id(); return $d; }
    protected function afterCreate(): void
    {
        $a = Broadcast::send($this->record);
        Notification::make()->title('Đã gửi tới ' . $a->recipient_count . ' người')->body('Email đi: ' . $a->email_ok . ' · Zalo đi: ' . $a->zalo_ok)->success()->send();
    }
    protected function getRedirectUrl(): string { return AnnouncementResource::getUrl('view', ['record' => $this->record]); }
}
