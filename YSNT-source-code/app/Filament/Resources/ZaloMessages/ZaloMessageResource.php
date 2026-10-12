<?php
namespace App\Filament\Resources\ZaloMessages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ZaloMessages\Pages\ListZaloMessages;
use App\Models\ZaloMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ZaloMessageResource extends Resource
{
    protected static ?string $model = ZaloMessage::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;
    protected static string|UnitEnum|null $navigationGroup = 'Zalo OA';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'tin đã ghi';
    protected static ?string $pluralModelLabel = 'Nhật ký gửi tin';
    protected static ?string $navigationLabel = 'Nhật ký gửi tin';
    public const STATUS = ['queued' => 'Chờ gửi', 'sent' => 'Đã gửi', 'failed' => 'Lỗi', 'skipped' => 'Bỏ qua', 'dry_run' => 'Chạy thử'];

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'content', 'accountant'], true); }
    public static function form(Schema $schema): Schema { return $schema; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with(['template', 'order']))
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('H:i d/m/Y')->sortable(),
                TextColumn::make('template.name')->label('Mẫu'),
                TextColumn::make('order.code')->label('Đơn')->url(fn ($record) => $record->order ? OrderResource::getUrl('edit', ['record' => $record->order]) : null)->placeholder('—'),
                TextColumn::make('phone')->label('Gửi tới')->formatStateUsing(fn ($state) => $state ? substr($state, 0, 5) . '***' . substr($state, -3) : '—'),
                TextColumn::make('payload')->label('Nội dung ghép')->wrap()->limit(120)
                    ->formatStateUsing(fn ($state) => collect(is_array($state) ? ($state['template_data'] ?? []) : (json_decode((string) $state, true)['template_data'] ?? []))->map(fn ($v, $k) => "$k: $v")->implode(' · ')),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => self::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'sent' => 'success', 'failed' => 'danger', 'dry_run' => 'info', default => 'gray' }),
                TextColumn::make('error')->label('Ghi chú')->limit(60)->placeholder(''),
                TextColumn::make('cost')->label('Chi phí')->formatStateUsing(fn ($state) => $state ? OrderResource::vnd($state) : '')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Trạng thái')->options(self::STATUS), SelectFilter::make('zalo_template_id')->label('Mẫu')->relationship('template', 'name')])
            ->emptyStateHeading('Chưa có tin nào')->emptyStateDescription('Khi có đơn web mới, hệ thống ghi tin xác nhận ở đây. Chưa kết nối Zalo thì ghi ở chế độ chạy thử.');
    }

    public static function getPages(): array { return ['index' => ListZaloMessages::route('/')]; }
}
