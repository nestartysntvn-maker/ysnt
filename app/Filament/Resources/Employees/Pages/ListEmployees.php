<?php
namespace App\Filament\Resources\Employees\Pages;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Hr\Staff;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Nhập từ Excel')->icon(Heroicon::OutlinedArrowUpTray)
                ->modalDescription('Tệp Excel (.xlsx) hoặc CSV. Dòng đầu là tiêu đề: Họ tên, Số điện thoại, Email, Phòng ban, Chức danh, Ngày vào làm (dd/mm/yyyy). Số đã có sẽ bỏ qua; phòng ban chưa có sẽ tự tạo.')
                ->schema([FileUpload::make('file')->label('Tệp danh sách')->disk('local')->directory('imports')->required()->maxSize(10240)
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain'])])
                ->action(function (array $data) {
                    $r = Staff::import(Storage::disk('local')->path($data['file']));
                    Notification::make()->title('Đã thêm ' . $r['created'] . ' nhân viên')->body($r['skipped'] ? 'Bỏ qua: ' . implode('; ', array_slice($r['skipped'], 0, 15)) . (count($r['skipped']) > 15 ? '…' : '') : null)
                        ->{$r['skipped'] ? 'warning' : 'success'}()->persistent()->send();
                }),
            CreateAction::make()->label('Thêm nhân viên'),
        ];
    }
}
