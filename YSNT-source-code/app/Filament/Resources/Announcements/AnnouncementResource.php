<?php
namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Hr\Hr;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'thông báo';
    protected static ?string $pluralModelLabel = 'Thông báo nội bộ';
    protected static ?string $navigationLabel = 'Gửi thông báo nội bộ';

    public static function canViewAny(): bool { return Hr::can('announce'); }
    public static function canEdit($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nội dung')->columnSpanFull()->schema([
                TextInput::make('title')->label('Tiêu đề')->required()->maxLength(255),
                Textarea::make('body')->label('Nội dung')->required()->rows(8),
                TextInput::make('link')->label('Đường dẫn kèm theo')->url()->maxLength(500),
                Toggle::make('pinned')->label('Ghim lên đầu bảng tin'),
            ]),
            Section::make('Gửi tới')->columns(2)->columnSpanFull()->schema([
                Radio::make('audience')->label('Người nhận')->options(Announcement::AUDIENCE)->default('all')->required()->live(),
                CheckboxList::make('channels')->label('Kênh')->options(Announcement::CHANNELS)->default(['workplace'])->required()
                    ->helperText('Email, Zalo chỉ gửi thật khi đã bật ở trang Máy chủ mail và Kết nối Zalo OA; chưa bật thì ghi nhật ký. Zalo chỉ tới người đã quan tâm OA công ty.'),
                Select::make('target_department_ids')->label('Phòng ban')->multiple()->options(fn () => Department::where('is_active', true)->pluck('name', 'id'))
                    ->visible(fn ($get) => $get('audience') === 'departments')->required(fn ($get) => $get('audience') === 'departments'),
                Select::make('target_employee_ids')->label('Nhân viên')->multiple()->searchable()->options(fn () => Employee::where('status', 'active')->orderBy('full_name')->pluck('full_name', 'id'))
                    ->visible(fn ($get) => $get('audience') === 'employees')->required(fn ($get) => $get('audience') === 'employees'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn ($query) => $query->withCount(['recipients as read_count' => fn ($q) => $q->whereNotNull('read_at')]))
            ->columns([
                TextColumn::make('title')->label('Thông báo')->weight('bold')->limit(60)->description(fn ($record) => $record->created_at->format('H:i d/m/Y') . ' · ' . ($record->sender?->name ?? '')),
                TextColumn::make('audience')->label('Gửi tới')->formatStateUsing(fn ($state) => Announcement::AUDIENCE[$state] ?? $state),
                TextColumn::make('channels')->label('Kênh')->formatStateUsing(fn ($state) => collect((array) $state)->map(fn ($c) => Announcement::CHANNELS[$c] ?? $c)->implode(', ')),
                TextColumn::make('recipient_count')->label('Người nhận')->alignCenter(),
                TextColumn::make('read_count')->label('Đã đọc')->alignCenter()->formatStateUsing(fn ($state, $record) => $state . ($record->recipient_count ? ' (' . round($state / $record->recipient_count * 100) . '%)' : '')),
                TextColumn::make('email_ok')->label('Email đi')->alignCenter(),
                TextColumn::make('zalo_ok')->label('Zalo đi')->alignCenter(),
            ])->recordUrl(fn ($record) => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Chưa gửi thông báo nào')->emptyStateDescription('Thông báo hiện trên Workplace của từng người và có thể gửi kèm email, Zalo.');
    }

    public static function getPages(): array { return ['index' => ListAnnouncements::route('/'), 'create' => CreateAnnouncement::route('/create'), 'view' => ViewAnnouncement::route('/{record}')]; }
}
