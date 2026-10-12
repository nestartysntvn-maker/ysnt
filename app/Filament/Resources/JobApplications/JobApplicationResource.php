<?php
namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\CreateJobApplication;
use App\Filament\Resources\JobApplications\Pages\EditJobApplication;
use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Hr\Hr;
use App\Models\JobApplication;
use App\Models\RecruitmentJob;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** pipeline as go365: Mới -> Hẹn phỏng vấn -> Đã nhận / Từ chối; each step sends its email (see the edit page actions) */
class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 7;
    protected static ?string $modelLabel = 'hồ sơ ứng viên';
    protected static ?string $pluralModelLabel = 'Ứng viên';

    public static function canViewAny(): bool { return Hr::can('recruit'); }
    public static function getNavigationBadge(): ?string { $n = JobApplication::where('status', 'new')->count(); return $n ? (string) $n : null; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ứng viên')->columns(3)->columnSpanFull()->schema([
                Select::make('job_id')->label('Tin tuyển dụng')->options(fn () => RecruitmentJob::orderByRaw("status = 'open' desc")->pluck('title', 'id'))->live()
                    ->afterStateUpdated(fn ($state, $set) => $state ? $set('position', RecruitmentJob::find($state)?->title) : null),
                TextInput::make('position')->label('Vị trí')->required()->maxLength(128),
                Placeholder::make('st')->label('Trạng thái')->content(fn (?JobApplication $record) => $record ? (JobApplication::STATUS[$record->status] ?? $record->status) . ($record->interview_at ? ' · PV ' . $record->interview_at->format('H:i d/m/Y') : '') : 'Mới'),
                TextInput::make('name')->label('Họ tên')->required()->maxLength(128),
                TextInput::make('phone')->label('Số điện thoại')->required()->tel()->maxLength(32),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                Select::make('experience')->label('Kinh nghiệm')->options(JobApplication::EXPERIENCE),
                TextInput::make('cv_url')->label('Đường dẫn CV')->url()->maxLength(500),
                Placeholder::make('cv')->label('CV đã nộp')->content(fn (?JobApplication $record) => $record?->cv_path ? new HtmlString('<a target="_blank" style="text-decoration:underline" href="/quan-tri/cv/' . $record->id . '">Mở CV</a>') : 'Không có tệp'),
                Textarea::make('message')->label('Giới thiệu của ứng viên')->rows(3)->columnSpanFull(),
                Textarea::make('notes')->label('Ghi chú nội bộ của nhân sự')->rows(3)->columnSpanFull(),
                Placeholder::make('src')->label('Nguồn')->content(fn (?JobApplication $record) => $record ? (JobApplication::SOURCE[$record->source] ?? $record->source) . ($record->referrer ? ' · ' . $record->referrer->full_name : '') . ' · ' . $record->code : 'Nhân sự nhập'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn ($query) => $query->with(['job', 'referrer']))->columns([
            TextColumn::make('name')->label('Ứng viên')->weight('bold')->searchable(['name', 'phone', 'email', 'code'])->description(fn ($record) => $record->phone . ($record->email ? ' · ' . $record->email : '')),
            TextColumn::make('position')->label('Vị trí')->wrap(),
            TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => JobApplication::STATUS[$state] ?? $state)
                ->color(fn ($state) => match ($state) { 'new' => 'info', 'interview' => 'warning', 'hired' => 'success', default => 'gray' }),
            TextColumn::make('interview_at')->label('Phỏng vấn')->dateTime('H:i d/m')->placeholder('—'),
            TextColumn::make('source')->label('Nguồn')->formatStateUsing(fn ($state, $record) => (JobApplication::SOURCE[$state] ?? $state) . ($record->referrer ? ': ' . $record->referrer->full_name : '')),
            TextColumn::make('created_at')->label('Nộp lúc')->since()->sortable(),
        ])->filters([SelectFilter::make('status')->label('Trạng thái')->options(JobApplication::STATUS), SelectFilter::make('job_id')->label('Vị trí')->relationship('job', 'title'),
            SelectFilter::make('source')->label('Nguồn')->options(JobApplication::SOURCE)])
            ->recordActions([EditAction::make()->label('Xem, xử lý')])
            ->emptyStateHeading('Chưa có hồ sơ')->emptyStateDescription('Hồ sơ nộp ở trang /tuyen-dung và người được nhân viên giới thiệu từ Workplace sẽ về đây.');
    }

    public static function getPages(): array { return ['index' => ListJobApplications::route('/'), 'create' => CreateJobApplication::route('/create'), 'edit' => EditJobApplication::route('/{record}/edit')]; }
}
