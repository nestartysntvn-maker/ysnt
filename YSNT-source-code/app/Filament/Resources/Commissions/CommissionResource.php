<?php
namespace App\Filament\Resources\Commissions;

use App\Filament\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Commission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static string|UnitEnum|null $navigationGroup = 'Đại sứ & hoa hồng';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'hoa hồng';
    protected static ?string $pluralModelLabel = 'Hoa hồng từng đơn';
    protected static ?string $navigationLabel = 'Hoa hồng từng đơn';

    public const STATUS = ['pending' => 'Tạm tính', 'approved' => 'Đã duyệt', 'paid' => 'Đã trả', 'cancelled' => 'Đã hủy'];

    public static function form(Schema $schema): Schema { return $schema; }

    public static function table(Table $table): Table
    {
        $approve = fn (Commission $record) => $record->update(['status' => 'approved', 'approved_at' => now()]);
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with(['order', 'ambassador']))
            ->columns([
                TextColumn::make('order.code')->label('Đơn')->searchable()
                    ->url(fn ($record) => $record->order ? OrderResource::getUrl('edit', ['record' => $record->order]) : null),
                TextColumn::make('ambassador.name')->label('Đại sứ')->description(fn ($record) => $record->ambassador?->ref_code)->searchable(),
                TextColumn::make('level')->label('Cấp')->formatStateUsing(fn ($state) => $state === 'direct' ? 'Trực tiếp' : 'Đầu mối'),
                TextColumn::make('base_amount')->label('Doanh số tính')->formatStateUsing(fn ($state) => OrderResource::vnd($state))->alignEnd(),
                TextColumn::make('rate_percent')->label('%')->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.') . '%')->alignCenter(),
                TextColumn::make('amount')->label('Hoa hồng')->formatStateUsing(fn ($state) => OrderResource::vnd($state))->alignEnd()->weight('bold')->sortable(),
                TextColumn::make('order.status')->label('Trạng thái đơn')->formatStateUsing(fn ($state) => OrderResource::STATUS[$state] ?? $state),
                TextColumn::make('status')->label('Hoa hồng')->badge()->formatStateUsing(fn ($state) => self::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'approved' => 'info', 'paid' => 'success', 'cancelled' => 'danger', default => 'warning' }),
                TextColumn::make('created_at')->label('Ngày')->date('d/m/Y')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Trạng thái')->options(self::STATUS), SelectFilter::make('ambassador_id')->label('Đại sứ')->relationship('ambassador', 'name')->searchable()])
            ->recordActions([
                Action::make('approve')->label('Duyệt')->requiresConfirmation()->modalDescription('Chỉ nên duyệt khi đơn đã hoàn thành.')
                    ->visible(fn (Commission $record) => $record->status === 'pending')->action($approve),
            ])
            ->toolbarActions([
                BulkAction::make('approveMany')->label('Duyệt các dòng đã chọn')->requiresConfirmation()
                    ->action(fn (Collection $rs) => $rs->where('status', 'pending')->each($approve)),
            ])
            ->emptyStateHeading('Chưa có hoa hồng')->emptyStateDescription('Hoa hồng được tạo tự động khi có đơn mua qua mã đại sứ.');
    }

    public static function getPages(): array { return ['index' => ListCommissions::route('/')]; }
}
