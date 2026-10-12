<?php
namespace App\Filament\Resources\RecruitmentJobs;

use App\Filament\Resources\RecruitmentJobs\Pages\CreateRecruitmentJob;
use App\Filament\Resources\RecruitmentJobs\Pages\EditRecruitmentJob;
use App\Filament\Resources\RecruitmentJobs\Pages\ListRecruitmentJobs;
use App\Hr\Hr;
use App\Models\Department;
use App\Models\RecruitmentJob;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use UnitEnum;

class RecruitmentJobResource extends Resource
{
    protected static ?string $model = RecruitmentJob::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 6;
    protected static ?string $modelLabel = 'tin tuyển dụng';
    protected static ?string $pluralModelLabel = 'Tin tuyển dụng';

    public static function canViewAny(): bool { return Hr::can('recruit'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('title')->label('Vị trí')->required()->maxLength(255)->columnSpan(2),
                Select::make('status')->label('Trạng thái')->options(RecruitmentJob::STATUS)->default('draft')->required()->helperText('"Đang tuyển" mới hiện trên trang /tuyen-dung và Google Jobs.'),
                Select::make('department_id')->label('Phòng ban')->options(fn () => Department::where('is_active', true)->pluck('name', 'id')),
                Select::make('employment_type')->label('Hình thức')->options(RecruitmentJob::TYPES)->default('FULL_TIME')->required(),
                TextInput::make('location')->label('Nơi làm việc')->default('Nha Trang')->maxLength(255),
                TextInput::make('salary')->label('Mức lương')->placeholder('8-12 triệu + hoa hồng')->maxLength(255),
                DatePicker::make('deadline')->label('Hạn nộp hồ sơ')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
                Textarea::make('description')->label('Mô tả công việc')->rows(6)->columnSpanFull(),
                Textarea::make('requirements')->label('Yêu cầu (mỗi dòng một ý)')->rows(5)->columnSpanFull(),
                Textarea::make('benefits')->label('Quyền lợi')->rows(4)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('title')->label('Vị trí')->weight('bold')->searchable()->description(fn ($record) => $record->department?->name),
            TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => RecruitmentJob::STATUS[$state] ?? $state)
                ->color(fn ($state) => match ($state) { 'open' => 'success', 'paused' => 'warning', default => 'gray' }),
            TextColumn::make('applications_count')->label('Hồ sơ')->counts('applications')->alignCenter(),
            TextColumn::make('deadline')->label('Hạn nộp')->date('d/m/Y')->placeholder('—'),
            TextColumn::make('updated_at')->label('Cập nhật')->since(),
        ])->filters([SelectFilter::make('status')->label('Trạng thái')->options(RecruitmentJob::STATUS)])->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array { return ['index' => ListRecruitmentJobs::route('/'), 'create' => CreateRecruitmentJob::route('/create'), 'edit' => EditRecruitmentJob::route('/{record}/edit')]; }
}
