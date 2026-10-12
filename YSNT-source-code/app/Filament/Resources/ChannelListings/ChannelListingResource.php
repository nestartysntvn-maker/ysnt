<?php
namespace App\Filament\Resources\ChannelListings;

use App\Channels\ChannelSync;
use App\Filament\Resources\ChannelListings\Pages\CreateChannelListing;
use App\Filament\Resources\ChannelListings\Pages\EditChannelListing;
use App\Filament\Resources\ChannelListings\Pages\ListChannelListings;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\ChannelListing;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ChannelListingResource extends Resource
{
    protected static ?string $model = ChannelListing::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;
    protected static string|UnitEnum|null $navigationGroup = 'Kết nối sàn';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'ghép sản phẩm';
    protected static ?string $pluralModelLabel = 'Ghép sản phẩm với sàn';
    protected static ?string $navigationLabel = 'Ghép sản phẩm với sàn';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->description('Mỗi quy cách trên web ghép với đúng 1 SKU trên gian hàng. Đơn từ sàn về sẽ trừ kho theo quy cách đã ghép.')
            ->columns(3)->columnSpanFull()->schema([
                Select::make('sales_channel_id')->label('Gian hàng')->relationship('channel', 'name')->required()->preload(),
                Select::make('variant_id')->label('Quy cách trên web')->options(fn () => StockMovementResource::variantOptions())->searchable()->required()->columnSpan(2),
                TextInput::make('platform_item_id')->label('Mã sản phẩm trên sàn (item/product ID)'),
                TextInput::make('platform_sku_id')->label('Mã SKU trên sàn (sku/model ID)'),
                TextInput::make('platform_sku')->label('SKU người bán trên sàn'),
                Toggle::make('sync_stock')->label('Đẩy tồn quy cách này')->default(true)->inline(false),
                TextInput::make('stock_buffer')->label('Giữ lại (không đẩy lên sàn)')->numeric()->minValue(0)->default(0)->helperText('Ví dụ giữ 5 hộp cho showroom'),
            ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['channel', 'variant.product']))
            ->columns([
                TextColumn::make('channel.name')->label('Gian hàng')->description(fn ($record) => ChannelSync::PLATFORMS[$record->channel?->platform] ?? ''),
                TextColumn::make('variant.product.name')->label('Quy cách trên web')->description(fn ($record) => $record->variant?->name . ' · ' . $record->variant?->sku)->searchable(),
                TextColumn::make('platform_sku_id')->label('SKU ID trên sàn')->placeholder('—')->searchable(),
                TextColumn::make('variant.stock_qty')->label('Tồn web')->placeholder('Chưa theo dõi')->alignCenter(),
                TextColumn::make('stock_buffer')->label('Giữ lại')->alignCenter(),
                IconColumn::make('sync_stock')->label('Đẩy tồn')->boolean(),
                TextColumn::make('last_synced_at')->label('Đồng bộ')->since()->placeholder('Chưa'),
            ])
            ->filters([SelectFilter::make('sales_channel_id')->label('Gian hàng')->relationship('channel', 'name')])
            ->recordActions([EditAction::make()->label('Sửa')])
            ->emptyStateHeading('Chưa ghép sản phẩm nào')->emptyStateDescription('Tạo gian hàng trước, rồi ghép từng quy cách với SKU trên sàn.');
    }

    public static function getPages(): array { return ['index' => ListChannelListings::route('/'), 'create' => CreateChannelListing::route('/create'), 'edit' => EditChannelListing::route('/{record}/edit')]; }
}
