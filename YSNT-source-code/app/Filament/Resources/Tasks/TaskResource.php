<?php
namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Hr\Hr;
use App\Hr\Tasks;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** anyone on /admin can give work; admin and HR see all tasks, others see what they gave */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 0;
    protected static ?string $modelLabel = 'việc';
    protected static ?string $pluralModelLabel = 'Giao việc';
    protected static ?string $navigationLabel = 'Giao việc';

    public static function seesAll(): bool { return Hr::can('staff'); }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['employee.department', 'assignerUser', 'assignerEmployee']);
        return self::seesAll() ? $q : $q->where('assigned_by_user_id', auth()->id());
    }

    public static function getNavigationBadge(): ?string
    {
        $n = self::getEloquentQuery()->open()->whereNotNull('due_at')->where('due_at', '<', now())->count();
        return $n ? (string) $n : null;
    }
    public static function getNavigationBadgeColor(): ?string { return 'danger'; }
    public static function getNavigationBadgeTooltip(): ?string { return 'Việc quá hạn'; }

    private static function people() { return Employee::with('department')->where('status', 'active')->whereNotIn('id', Hr::demoIds() ?: [0])->orderBy('full_name')->get()
        ->mapWithKeys(fn ($e) => [$e->id => $e->full_name . ($e->department ? ' · ' . $e->department->name : '')]); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Việc')->columns(3)->columnSpanFull()->schema([
                TextInput::make('title')->label('Việc cần làm')->required()->maxLength(255)->columnSpan(2),
                Select::make('priority')->label('Ưu tiên')->options(Task::PRIORITY)->default('normal')->required(),
                Select::make('employee_ids')->label('Giao cho (chọn nhiều người được)')->multiple()->searchable()->options(fn () => self::people())->required()->visibleOn('create')->columnSpan(2)
                    ->helperText('Mỗi người nhận một việc riêng để theo dõi.'),
                Select::make('department_pick')->label('Hoặc cả phòng')->options(fn () => Department::where('is_active', true)->pluck('name', 'id'))->visibleOn('create')->dehydrated(false)->live()
                    ->afterStateUpdated(fn ($state, $set, $get) => $state ? $set('employee_ids', array_values(array_unique(array_merge((array) $get('employee_ids'), Employee::where('status', 'active')->where('department_id', $state)->whereNotIn('id', Hr::demoIds() ?: [0])->pluck('id')->all())))) : null),
                Select::make('employee_id')->label('Người làm')->options(fn () => self::people())->searchable()->required()->visibleOn('edit'),
                Select::make('status')->label('Tình trạng')->options(Task::STATUS)->required()->visibleOn('edit'),
                DateTimePicker::make('due_at')->label('Hạn chót (giờ Việt Nam)')->seconds(false)->timezone(Tasks::TZ),
                Select::make('remind_before_hours')->label('Nhắc việc')->options(Task::REMIND)->default(24)->required(),
                TextInput::make('link')->label('Liên kết kèm theo')->url()->maxLength(500),
                Textarea::make('description')->label('Mô tả, kết quả cần đạt')->rows(4)->columnSpanFull(),
            ]),
            Section::make('Trao đổi, lịch sử')->columnSpanFull()->visibleOn('edit')->schema([
                Placeholder::make('log')->hiddenLabel()->content(fn (?Task $record) => new HtmlString($record ? $record->updates()->with(['employee', 'user'])->get()->map(fn ($u) =>
                    '<div style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.15)"><span style="opacity:.6;font-size:12px">' . e($u->created_at->tz(Tasks::TZ)->format('H:i d/m')) . ' · ' . e($u->kind === 'reminder' ? 'Hệ thống' : $u->author())
                    . ($u->channels ? ' · ' . e($u->channels) : '') . '</span><br>' . nl2br(e((string) $u->body)) . '</div>')->implode('') ?: 'Chưa có' : '')),
                Textarea::make('comment')->label('Nhắn người làm')->rows(2)->dehydrated(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')->columns([
            TextColumn::make('title')->label('Việc')->weight('bold')->wrap()->searchable()->description(fn ($record) => $record->employee?->full_name . ($record->employee?->department ? ' · ' . $record->employee->department->name : '')),
            TextColumn::make('due_at')->label('Hạn')->dateTime('H:i d/m/Y', Tasks::TZ)->sortable()->placeholder('Không hạn')->color(fn ($record) => $record->isOverdue() ? 'danger' : null)
                ->description(fn ($record) => $record->isOverdue() ? 'Quá hạn' : null),
            TextColumn::make('status')->label('Tình trạng')->badge()->formatStateUsing(fn ($state) => Task::STATUS[$state] ?? $state)
                ->color(fn ($state) => match ($state) { 'done' => 'success', 'doing' => 'info', 'cancelled' => 'gray', default => 'warning' }),
            TextColumn::make('priority')->label('Ưu tiên')->formatStateUsing(fn ($state) => Task::PRIORITY[$state] ?? $state),
            TextColumn::make('assigner')->label('Người giao')->state(fn ($record) => $record->assignerName()),
        ])->filters([
            SelectFilter::make('status')->label('Tình trạng')->options(Task::STATUS)->multiple()->default(['todo', 'doing']),
            SelectFilter::make('employee_id')->label('Người làm')->options(fn () => self::people())->searchable(),
            Filter::make('overdue')->label('Chỉ việc quá hạn')->query(fn ($query) => $query->open()->whereNotNull('due_at')->where('due_at', '<', now())),
            Filter::make('mine')->label('Việc tôi giao')->query(fn ($query) => $query->where('assigned_by_user_id', auth()->id())),
        ])->recordActions([
            Action::make('remind')->label('Nhắc ngay')->icon(Heroicon::OutlinedBellAlert)->visible(fn ($record) => $record->isOpen())
                ->action(function (Task $record) { Tasks::notifyAssignee($record, 'Nhắc việc', 'Hạn: ' . Tasks::when($record->due_at)); Notification::make()->title('Đã nhắc ' . $record->employee->full_name)->success()->send(); }),
            Action::make('done')->label('Đã xong')->icon(Heroicon::OutlinedCheck)->visible(fn ($record) => $record->isOpen())->requiresConfirmation()
                ->action(fn (Task $record) => Tasks::setStatus($record, 'done', 'Người giao xác nhận xong', null, auth()->user())),
            EditAction::make()->label('Xem, sửa'),
        ])->emptyStateHeading('Chưa giao việc nào')->emptyStateDescription('Giao việc cho từng người hoặc cả phòng. Người nhận thấy trên Workplace, được nhắc trước hạn qua Zalo, email.');
    }

    public static function getPages(): array { return ['index' => ListTasks::route('/'), 'create' => CreateTask::route('/create'), 'edit' => EditTask::route('/{record}/edit')]; }
}
