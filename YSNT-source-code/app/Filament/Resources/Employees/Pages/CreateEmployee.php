<?php
namespace App\Filament\Resources\Employees\Pages;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Hr\Staff;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
    protected function mutateFormDataBeforeCreate(array $d): array { $d['must_change_password'] = true; $d['onboarding_status'] = 'approved'; return $d; }
    protected function afterCreate(): void
    {
        if ($this->record->password) return;
        Notification::make()->title('Mật khẩu Workplace tạm: ' . Staff::resetPassword($this->record))->body('Đưa cho ' . $this->record->full_name . '; đăng nhập lần đầu phải đổi.')->success()->persistent()->send();
    }
}
