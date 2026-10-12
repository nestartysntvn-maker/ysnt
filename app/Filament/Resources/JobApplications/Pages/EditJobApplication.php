<?php
namespace App\Filament\Resources\JobApplications\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Hr\Recruit;
use App\Hr\Staff;
use App\Models\Employee;
use App\Support\Company;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditJobApplication extends EditRecord
{
    protected static string $resource = JobApplicationResource::class;

    protected function getHeaderActions(): array
    {
        $a = fn () => $this->record;
        return [
            Action::make('interview')->label('Hẹn phỏng vấn')->icon(Heroicon::OutlinedCalendarDays)->visible(fn () => in_array($a()->status, ['new', 'interview'], true))
                ->fillForm(fn () => ['at' => $a()->interview_at?->tz('Asia/Ho_Chi_Minh'), 'where' => $a()->interview_location, 'host' => $a()->interview_host])
                ->schema([DateTimePicker::make('at')->label('Thời gian (giờ Việt Nam)')->required()->seconds(false)->timezone('Asia/Ho_Chi_Minh'),
                    TextInput::make('where')->label('Địa điểm hoặc link phỏng vấn online')->placeholder(Company::get('showroom_address')),
                    TextInput::make('host')->label('Người phỏng vấn')->placeholder('Đại diện tuyển dụng ' . Company::get('brand_name'))])
                ->modalDescription('Gửi thư mời tới ứng viên và báo nhân sự. Đổi giờ thì thư mời gửi lại.')
                ->action(function (array $data) use ($a) {
                    $changed = $a()->status !== 'interview' || ! $a()->interview_at?->equalTo($data['at']);
                    $a()->update(['status' => 'interview', 'interview_at' => $data['at'], 'interview_location' => $data['where'] ?: null, 'interview_host' => $data['host'] ?: null]);
                    if ($changed) Recruit::interview($a()->fresh());
                    Notification::make()->title($changed ? 'Đã hẹn và gửi thư mời' : 'Đã lưu, giờ không đổi nên không gửi lại')->success()->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('hire')->label('Nhận việc, gửi thư')->icon(Heroicon::OutlinedCheckCircle)->color('success')->visible(fn () => in_array($a()->status, ['new', 'interview'], true))->requiresConfirmation()
                ->modalDescription('Gửi thư trúng tuyển kèm đường dẫn (14 ngày) để người mới tự điền hồ sơ nhân viên; nhân sự duyệt sau.')
                ->action(function () use ($a) { $a()->update(['status' => 'hired']); $link = Recruit::hired($a()->fresh());
                    Notification::make()->title('Đã gửi thư trúng tuyển')->body('Đường dẫn điền hồ sơ: ' . $link)->success()->persistent()->send(); }),
            Action::make('reject')->label('Từ chối')->icon(Heroicon::OutlinedXCircle)->color('danger')->visible(fn () => in_array($a()->status, ['new', 'interview'], true))->requiresConfirmation()
                ->modalDescription('Gửi thư phản hồi lịch sự tới ứng viên.')
                ->action(function () use ($a) { $was = (bool) $a()->interview_at; $a()->update(['status' => 'rejected']); Recruit::rejected($a()->fresh(), $was); Notification::make()->title('Đã từ chối và gửi thư')->success()->send(); }),
            Action::make('toStaff')->label('Tạo hồ sơ nhân sự')->icon(Heroicon::OutlinedUserPlus)->visible(fn () => $a()->status === 'hired' && ! $a()->employee_id)->requiresConfirmation()
                ->modalDescription('Tạo luôn hồ sơ (không chờ người mới tự điền). Mật khẩu Workplace tạm hiện ra sau khi tạo.')
                ->action(function () use ($a) {
                    $phone = \App\Models\Customer::normalizePhone($a()->phone);
                    if (! $phone || Employee::where('phone', $phone)->exists()) { Notification::make()->title('Số điện thoại không hợp lệ hoặc đã có hồ sơ nhân sự')->danger()->send(); return; }
                    $e = Employee::create(['full_name' => $a()->name, 'phone' => $phone, 'email' => $a()->email, 'job_title' => $a()->position, 'department_id' => $a()->job?->department_id,
                        'status' => 'active', 'onboarding_status' => 'approved', 'hire_date' => now()->toDateString()]);
                    $a()->update(['employee_id' => $e->id, 'notes' => trim($a()->notes . "\nĐã tạo hồ sơ nhân sự " . $e->fresh()->code . ' ngày ' . now()->format('d/m/Y'))]);
                    Notification::make()->title('Đã tạo ' . $e->fresh()->code . '. Mật khẩu Workplace tạm: ' . Staff::resetPassword($e))->success()->persistent()->send();
                    $this->redirect(EmployeeResource::getUrl('edit', ['record' => $e]));
                }),
            DeleteAction::make()->label('Xóa hồ sơ'),
        ];
    }
}
