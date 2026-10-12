<?php
namespace App\Filament\Resources\Tasks\Pages;
use App\Filament\Resources\Tasks\TaskResource;
use App\Hr\Tasks;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;
    protected static ?string $title = 'Giao việc';
    protected static bool $canCreateAnother = false;
    protected function getCreateFormAction(): \Filament\Actions\Action { return parent::getCreateFormAction()->label('Giao việc'); }
    /** one task per person, each notified */
    protected function handleRecordCreation(array $data): Model
    {
        $tasks = Tasks::assign($data['employee_ids'], $data, auth()->user());
        Notification::make()->title('Đã giao việc cho ' . count($tasks) . ' người')->success()->send();
        return $tasks[0];
    }
    protected function getCreatedNotification(): ?Notification { return null; }
    protected function getRedirectUrl(): string { return TaskResource::getUrl(); }
}
