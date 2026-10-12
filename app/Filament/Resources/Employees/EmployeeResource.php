<?php
namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Hr\Hr;
use App\Hr\Staff;
use App\Models\Ambassador;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'nhân viên';
    protected static ?string $pluralModelLabel = 'Hồ sơ nhân sự';
    protected static ?string $navigationLabel = 'Hồ sơ nhân sự';

    public static function canViewAny(): bool { return Hr::can('staff'); }

    public static function getNavigationBadge(): ?string { $n = Employee::where('onboarding_status', 'pending')->count(); return $n ? (string) $n : null; }

    public static function form(Schema $schema): Schema
    {
        $files = fn (?Employee $r, string $k, string $label) => $r && $r->priv($k) ? collect((array) $r->priv($k))->keys()
            ->map(fn ($i) => '<a style="text-decoration:underline" target="_blank" href="/quan-tri/ho-so/' . $r->id . '/' . $k . (is_array($r->priv($k)) ? '/' . $i : '') . '">' . $label . (is_array($r->priv($k)) ? ' ' . ($i + 1) : '') . '</a>')->implode(' · ') : null;
        return $schema->components([
            Section::make('Thông tin cơ bản')->columns(3)->columnSpanFull()->schema([
                TextInput::make('full_name')->label('Họ tên (như CCCD)')->required()->maxLength(255),
                TextInput::make('alias')->label('Tên thường gọi')->maxLength(64),
                TextInput::make('code')->label('Mã nhân viên')->disabled()->dehydrated(false)->placeholder('Tự tạo'),
                TextInput::make('phone')->label('Số điện thoại (đăng nhập Workplace)')->required()->tel()->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => \App\Models\Customer::normalizePhone($state) ?? $state),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                Select::make('department_id')->label('Phòng ban')->options(fn () => Department::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))->searchable(),
                TextInput::make('job_title')->label('Chức danh')->maxLength(128),
                Select::make('manager_id')->label('Quản lý trực tiếp')->searchable()
                    ->options(fn (?Employee $record) => Employee::where('status', 'active')->when($record, fn ($q) => $q->where('id', '!=', $record->id))->orderBy('full_name')->pluck('full_name', 'id')),
                DatePicker::make('hire_date')->label('Ngày vào làm')->native(false)->displayFormat('d/m/Y'),
                Select::make('status')->label('Trạng thái')->options(Employee::STATUS)->default('active')->required()->live(),
                Select::make('user_id')->label('Tài khoản trang quản trị (nếu có)')->options(fn () => User::orderBy('name')->pluck('name', 'id'))->searchable()
                    ->helperText('Chỉ người làm việc trên trang quản trị mới cần.'),
                Select::make('ambassador_id')->label('Mã giới thiệu nội bộ')->searchable()
                    ->options(fn () => Ambassador::where('type', 'staff')->get()->mapWithKeys(fn ($a) => [$a->id => $a->ref_code . ' · ' . $a->name])),
                Placeholder::make('onb')->label('Hồ sơ tự điền')->visible(fn (?Employee $record) => $record?->onboarding_status === 'pending')
                    ->content('Đang chờ duyệt: bấm "Duyệt hồ sơ" ở đầu trang để mở Workplace cho người này.'),
            ]),
            Section::make('Đăng nhập Workplace')->columns(2)->columnSpanFull()->schema([
                TextInput::make('password')->label('Mật khẩu mới')->password()->revealable()->minLength(8)->dehydrated(fn ($state) => filled($state))
                    ->helperText('Để trống: giữ nguyên. Người nhận phải đổi mật khẩu ở lần đăng nhập đầu.'),
                Placeholder::make('last')->label('Đăng nhập gần nhất')->content(fn (?Employee $record) => $record?->last_login_at?->format('H:i d/m/Y') ?? 'Chưa đăng nhập'),
            ]),
            Section::make('Liên hệ, kênh chat')->columns(3)->columnSpanFull()->visible(fn () => Hr::open('contact'))->schema([
                TextInput::make('profile.company_email')->label('Email công ty')->email(),
                TextInput::make('profile.hotline_ext')->label('Số máy lẻ'),
                TextInput::make('profile.personal_phone')->label('Số cá nhân khác'),
                TextInput::make('profile.zalo')->label('Zalo'),
                TextInput::make('profile.whatsapp')->label('WhatsApp'),
                TextInput::make('profile.wechat')->label('WeChat'),
                TextInput::make('profile.telegram')->label('Telegram'),
                TagsInput::make('profile.extra_phones')->label('Số điện thoại khác'),
                TagsInput::make('profile.extra_emails')->label('Email khác'),
            ]),
            Section::make('Bảo hiểm')->columns(2)->columnSpanFull()->visible(fn () => Hr::open('insurance'))->schema([
                TextInput::make('profile.social_insurance_no')->label('Số BHXH')->maxLength(20),
                TextInput::make('profile.health_insurance_no')->label('Số BHYT')->maxLength(20),
            ]),
            Section::make('Lương, hoa hồng, phụ cấp')->columns(3)->columnSpanFull()->visible(fn () => Hr::open('salary') && Hr::can('salary'))->schema([
                TextInput::make('base_salary')->label('Lương cơ bản (đồng/tháng)')->numeric()->minValue(0),
                Toggle::make('commission_enabled')->label('Hưởng hoa hồng bán hàng')->inline(false),
                TextInput::make('commission_pct')->label('Tỷ lệ hoa hồng riêng (%)')->numeric()->minValue(0.1)->maxValue(20)->helperText('Để trống: theo chính sách chung'),
                Repeater::make('allowances')->label('Phụ cấp')->columnSpanFull()->columns(2)->defaultItems(0)->schema([
                    TextInput::make('type')->label('Loại')->required(), TextInput::make('amount')->label('Số tiền/tháng')->numeric()->required()]),
            ]),
            Section::make('Thông tin cá nhân (chỉ Quản trị xem)')->columns(3)->columnSpanFull()->visible(fn () => Hr::open('private') && Hr::can('private'))->schema([
                DatePicker::make('private.birthday')->label('Ngày sinh')->native(false)->displayFormat('d/m/Y')->format('Y-m-d'),
                TextInput::make('private.id_number')->label('Số CCCD'),
                TextInput::make('private.address')->label('Địa chỉ')->columnSpan(2),
                TextInput::make('private.emergency_name')->label('Người liên hệ khẩn cấp'),
                TextInput::make('private.emergency_phone')->label('SĐT khẩn cấp'),
                TextInput::make('private.emergency_relation')->label('Quan hệ'),
                TextInput::make('private.bank_account')->label('Số tài khoản'),
                TextInput::make('private.bank_name')->label('Ngân hàng'),
                TextInput::make('private.bank_holder')->label('Chủ tài khoản'),
                Placeholder::make('docs')->label('Giấy tờ đã nộp')->columnSpanFull()->content(fn (?Employee $record) => new HtmlString(collect([
                    $files($record, 'id_front', 'CCCD trước'), $files($record, 'id_back', 'CCCD sau'), $files($record, 'degrees', 'Bằng cấp'), $files($record, 'health', 'Giấy khám SK')])->filter()->implode(' · ') ?: 'Chưa có')),
            ]),
            Section::make('Nghỉ việc')->columns(2)->columnSpanFull()->visible(fn ($get) => Hr::open('offboarding') && $get('status') !== 'active')->schema([
                Select::make('termination_type')->label('Loại')->options(Employee::TERMINATION),
                DatePicker::make('last_working_day')->label('Ngày làm việc cuối')->native(false)->displayFormat('d/m/Y'),
                Textarea::make('termination_reason')->label('Lý do')->rows(2)->maxLength(500),
                Textarea::make('asset_return_note')->label('Bàn giao tài sản, công việc')->rows(2)->maxLength(500),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('full_name')->modifyQueryUsing(fn ($query) => $query->with(['department', 'manager']))
            ->columns([
                TextColumn::make('full_name')->label('Nhân viên')->weight('bold')->searchable(['full_name', 'phone', 'code', 'email'])->description(fn ($record) => $record->code . ' · ' . $record->phone),
                TextColumn::make('department.name')->label('Phòng ban')->placeholder('—')->sortable(),
                TextColumn::make('job_title')->label('Chức danh')->placeholder('—'),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state, $record) => $record->onboarding_status === 'pending' ? 'Chờ duyệt' : (Employee::STATUS[$state] ?? $state))
                    ->color(fn ($state, $record) => $record->onboarding_status === 'pending' ? 'warning' : ($state === 'active' ? 'success' : 'gray')),
                TextColumn::make('hire_date')->label('Vào làm')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('last_login_at')->label('Workplace')->since()->placeholder('Chưa vào'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(Employee::STATUS)->default('active'),
                SelectFilter::make('department_id')->label('Phòng ban')->relationship('department', 'name'),
                SelectFilter::make('onboarding_status')->label('Hồ sơ tự điền')->options(['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt']),
            ])
            ->recordActions([
                Action::make('approve')->label('Duyệt hồ sơ')->icon(Heroicon::OutlinedCheckBadge)->visible(fn ($record) => $record->onboarding_status === 'pending')->requiresConfirmation()
                    ->action(fn (Employee $record) => Notification::make()->title('Đã duyệt. Mật khẩu Workplace tạm: ' . Staff::approve($record))->body('Đưa cho ' . $record->full_name . '; đăng nhập lần đầu phải đổi.')->success()->persistent()->send()),
                Action::make('leave')->label('Cho nghỉ việc')->icon(Heroicon::OutlinedArrowRightStartOnRectangle)->color('danger')->visible(fn ($record) => $record->status === 'active')
                    ->schema([Select::make('type')->label('Loại')->options(Employee::TERMINATION)->default('resign')->required(), DatePicker::make('day')->label('Ngày làm việc cuối')->default(now())->required()->native(false)->displayFormat('d/m/Y'),
                        Textarea::make('reason')->label('Lý do')->rows(2)])
                    ->action(function (Employee $record, array $data) { Staff::leave($record, $data['type'], $data['day'], $data['reason'] ?? null); Notification::make()->title('Đã cho nghỉ việc, Workplace đã ngưng')->success()->send(); }),
                EditAction::make()->label('Sửa'),
            ])
            ->toolbarActions([
                BulkAction::make('passwords')->label('Cấp mật khẩu Workplace')->icon(Heroicon::OutlinedKey)->requiresConfirmation()
                    ->modalDescription('Tạo mật khẩu tạm mới cho các người đã chọn và tải về danh sách để phát cho từng người. Mật khẩu cũ hết hiệu lực.')
                    ->action(function (Collection $records) {
                        $rows = $records->filter(fn ($e) => $e->status === 'active')->map(fn (Employee $e) => [$e->code, $e->full_name, $e->phone, Staff::resetPassword($e)])->values();
                        return response()->streamDownload(function () use ($rows) {
                            $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF"); fputcsv($f, ['Mã NV', 'Họ tên', 'Số điện thoại (đăng nhập)', 'Mật khẩu tạm']);
                            foreach ($rows as $r) fputcsv($f, $r); fclose($f);
                        }, 'mat-khau-workplace-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
                BulkAction::make('leaveMany')->label('Cho nghỉ việc')->color('danger')->requiresConfirmation()
                    ->action(function (Collection $records) { $records->where('status', 'active')->each(fn ($e) => Staff::leave($e)); Notification::make()->title('Đã cập nhật')->success()->send(); }),
            ])
            ->emptyStateHeading('Chưa có nhân viên')->emptyStateDescription('Thêm từng người, hoặc bấm "Nhập từ Excel" để đưa cả danh sách vào.');
    }

    public static function getPages(): array { return ['index' => ListEmployees::route('/'), 'create' => CreateEmployee::route('/create'), 'edit' => EditEmployee::route('/{record}/edit')]; }
}
