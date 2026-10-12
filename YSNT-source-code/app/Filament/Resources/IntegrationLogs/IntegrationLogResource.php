<?php
namespace App\Filament\Resources\IntegrationLogs;

use App\Filament\Resources\IntegrationLogs\Pages\ListIntegrationLogs;
use App\Models\IntegrationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class IntegrationLogResource extends Resource
{
    protected static ?string $model = IntegrationLog::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;
    protected static string|UnitEnum|null $navigationGroup = 'Kết nối & cấu hình';
    protected static ?int $navigationSort = 9;
    protected static ?string $modelLabel = 'dòng nhật ký';
    protected static ?string $pluralModelLabel = 'Nhật ký kết nối';
    protected static ?string $navigationLabel = 'Nhật ký kết nối';
    public const NAMES = ['mail' => 'Mail', 'meta_capi' => 'Meta Conversions API', 'meta_webhook' => 'Meta webhook', 'zalo_webhook' => 'Zalo webhook'];

    public static function canViewAny(): bool { return auth()->user()?->role === 'admin'; }
    public static function canCreate(): bool { return false; }
    public static function form(Schema $schema): Schema { return $schema; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->label('Thời gian')->dateTime('H:i:s d/m/Y'),
            TextColumn::make('integration')->label('Kết nối')->formatStateUsing(fn ($state) => self::NAMES[$state] ?? $state),
            TextColumn::make('action')->label('Việc'),
            TextColumn::make('ref')->label('Mã')->placeholder('—')->searchable(),
            TextColumn::make('status')->label('Kết quả')->badge()->formatStateUsing(fn ($state) => IntegrationLog::STATUS[$state] ?? $state)
                ->color(fn ($state) => match ($state) { 'ok' => 'success', 'error' => 'danger', 'dry_run' => 'info', default => 'gray' }),
            TextColumn::make('message')->label('Ghi chú')->limit(90)->wrap()->placeholder(''),
            TextColumn::make('payload')->label('Dữ liệu')->limit(90)->wrap()->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) $state),
        ])->filters([
            SelectFilter::make('integration')->label('Kết nối')->options(fn () => IntegrationLog::query()->distinct()->pluck('integration', 'integration')->map(fn ($v) => self::NAMES[$v] ?? $v)->all()),
            SelectFilter::make('status')->label('Kết quả')->options(IntegrationLog::STATUS),
        ])->emptyStateHeading('Chưa có dòng nào')->emptyStateDescription('Mail, Meta, webhook Zalo, Facebook ghi kết quả ở đây, kể cả khi đang chạy thử.');
    }

    public static function getPages(): array { return ['index' => ListIntegrationLogs::route('/')]; }
}
