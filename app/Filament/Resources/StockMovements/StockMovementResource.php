<?php
namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\CreateStockMovement;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;
    protected static string|UnitEnum|null $navigationGroup = 'Sản phẩm';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'phiếu kho';
    protected static ?string $pluralModelLabel = 'Nhập, xuất kho';
    protected static ?string $navigationLabel = 'Nhập, xuất kho';

    public const TYPES = ['in' => 'Nhập kho', 'out_damage' => 'Xuất hủy, hư hỏng', 'adjust' => 'Điều chỉnh kiểm kê', 'sale' => 'Xuất bán', 'return' => 'Khách trả hàng'];

    public static function variantOptions(): array
    {
        return ProductVariant::with('product')->get()->mapWithKeys(fn ($v) => [$v->id => $v->product->name . ' · ' . $v->name . ($v->sku ? " ({$v->sku})" : '')])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->columns(3)->columnSpanFull()->schema([
            Select::make('variant_id')->label('Quy cách')->options(fn () => self::variantOptions())->searchable()->required()->columnSpan(3)->live()
                ->helperText(fn ($get) => $get('variant_id') ? 'Tồn hiện tại: ' . (ProductVariant::find($get('variant_id'))?->stock_qty ?? 'chưa theo dõi') : null),
            Select::make('type')->label('Loại phiếu')->options(array_intersect_key(self::TYPES, array_flip(['in', 'out_damage', 'adjust'])))->required()->default('in'),
            TextInput::make('qty')->label('Số lượng')->numeric()->required()->helperText('Điều chỉnh: số dương là tăng, số âm là giảm.'),
            TextInput::make('note')->label('Ghi chú')->placeholder('Ví dụ: lô nhập từ nhà máy ngày…'),
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'user', 'order']))
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('H:i d/m/Y'),
                TextColumn::make('variant.product.name')->label('Sản phẩm')->description(fn ($record) => $record->variant?->name . ($record->variant?->sku ? ' · ' . $record->variant->sku : ''))->searchable(),
                TextColumn::make('type')->label('Loại')->badge()->formatStateUsing(fn ($state) => self::TYPES[$state] ?? $state)->color(fn ($state) => in_array($state, ['in', 'return']) ? 'success' : ($state === 'adjust' ? 'gray' : 'warning')),
                TextColumn::make('qty')->label('Số lượng')->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . $state)->alignCenter(),
                TextColumn::make('stock_after')->label('Tồn sau')->alignCenter(),
                TextColumn::make('order.code')->label('Đơn')->placeholder('—'),
                TextColumn::make('user.name')->label('Người làm')->placeholder('Hệ thống'),
                TextColumn::make('note')->label('Ghi chú')->placeholder('')->limit(40),
            ])
            ->filters([SelectFilter::make('type')->label('Loại')->options(self::TYPES)])
            ->emptyStateHeading('Chưa có phiếu kho')->emptyStateDescription('Bấm "Tạo phiếu kho" để nhập hàng. Tồn kho của quy cách sẽ cập nhật theo.');
    }

    public static function getPages(): array { return ['index' => ListStockMovements::route('/'), 'create' => CreateStockMovement::route('/create')]; }
}
