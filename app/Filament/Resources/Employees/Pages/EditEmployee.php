<?php
namespace App\Filament\Resources\Employees\Pages;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Hr\Staff;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;
    private ?string $oldStatus = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')->label('Duyệt hồ sơ')->icon(Heroicon::OutlinedCheckBadge)->visible(fn () => $this->record->onboarding_status === 'pending')->requiresConfirmation()
                ->action(function () { $p = Staff::approve($this->record); $this->refreshFormData(['status']); Notification::make()->title('Đã duyệt. Mật khẩu Workplace tạm: ' . $p)->success()->persistent()->send(); }),
            Action::make('reset')->label('Cấp lại mật khẩu Workplace')->icon(Heroicon::OutlinedKey)->requiresConfirmation()->visible(fn () => $this->record->status === 'active')
                ->action(fn () => Notification::make()->title('Mật khẩu tạm mới: ' . Staff::resetPassword($this->record))->body('Mật khẩu cũ đã hết hiệu lực.')->success()->persistent()->send()),
        ];
    }

    /** sections that are hidden (closed group or no permission) keep their stored values */
    protected function mutateFormDataBeforeSave(array $d): array
    {
        $this->oldStatus = $this->record->status;
        foreach (['profile', 'private'] as $k) if (array_key_exists($k, $d)) $d[$k] = array_merge($this->record->{$k} ?? [], $d[$k] ?? []);
        if (! empty($d['password'])) $d['must_change_password'] = true;
        return $d;
    }

    protected function afterSave(): void
    {
        if ($this->oldStatus === 'active' && $this->record->status !== 'active') Staff::afterLeave($this->record->fresh());
        if ($this->oldStatus !== 'active' && $this->record->status === 'active') Staff::back($this->record);
    }
}
