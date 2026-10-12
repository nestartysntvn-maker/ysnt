<?php
namespace App\Filament\Resources\KbQuestions;

use App\Filament\Resources\KbQuestions\Pages\ListKbQuestions;
use App\Hr\Hr;
use App\Models\KbDoc;
use App\Models\KbQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** what staff asked the internal AI: "không có trong tài liệu" rows show which procedures are missing */
class KbQuestionResource extends Resource
{
    protected static ?string $model = KbQuestion::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 5;
    protected static ?string $modelLabel = 'câu hỏi';
    protected static ?string $pluralModelLabel = 'Câu hỏi nhân viên hỏi AI';
    protected static ?string $navigationLabel = 'Câu hỏi hỏi AI';

    public static function canViewAny(): bool { return Hr::can('kb'); }
    public static function canCreate(): bool { return false; }
    public static function form(Schema $schema): Schema { return $schema; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn ($query) => $query->with('employee.department'))->columns([
            TextColumn::make('created_at')->label('Lúc')->dateTime('H:i d/m/Y'),
            TextColumn::make('employee.full_name')->label('Người hỏi')->description(fn ($record) => $record->employee?->department?->name),
            TextColumn::make('question')->label('Câu hỏi')->wrap()->limit(140)->searchable(),
            TextColumn::make('answer')->label('AI trả lời')->wrap()->limit(160),
            TextColumn::make('doc_ids')->label('Nguồn')->formatStateUsing(fn ($state) => KbDoc::whereIn('id', (array) $state)->pluck('title')->implode(', '))->placeholder('—')->wrap(),
            TextColumn::make('status')->label('Kết quả')->badge()->formatStateUsing(fn ($state) => KbQuestion::STATUS[$state] ?? $state)
                ->color(fn ($state) => match ($state) { 'answered' => 'success', 'unknown' => 'warning', 'error' => 'danger', default => 'gray' }),
            IconColumn::make('helpful')->label('Hữu ích')->boolean()->placeholder('—'),
        ])->filters([SelectFilter::make('status')->label('Kết quả')->options(KbQuestion::STATUS), TernaryFilter::make('helpful')->label('Nhân viên đánh giá hữu ích')])
            ->emptyStateHeading('Chưa có câu hỏi nào')->emptyStateDescription('Câu hỏi nhân viên gửi trong Workplace hiện ở đây. Lọc "Không có trong tài liệu" để biết cần viết thêm quy trình nào.');
    }

    public static function getPages(): array { return ['index' => ListKbQuestions::route('/')]; }
}
