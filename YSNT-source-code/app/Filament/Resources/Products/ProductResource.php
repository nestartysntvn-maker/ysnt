<?php
namespace App\Filament\Resources\Products;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;
    protected static string|UnitEnum|null $navigationGroup = 'Sản phẩm';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'sản phẩm';
    protected static ?string $pluralModelLabel = 'Sản phẩm';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Thông tin chung')->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('Tên sản phẩm')->required()->maxLength(255)->columnSpan(2)
                    ->live(onBlur: true)->afterStateUpdated(fn ($state, $set, $get, $record) => $record ? null : $set('slug', Str::slug($state))),
                TextInput::make('slug')->label('Đường dẫn')->required()->unique(ignoreRecord: true)->helperText('Dùng trong link /san-pham/…'),
                Select::make('category_id')->label('Nhóm')->relationship('category', 'name')->searchable()->preload()->required(),
                Select::make('brand_id')->label('Nhãn hàng')->relationship('brand', 'name')->preload(),
                Select::make('status')->label('Trạng thái')->options(['active' => 'Đang bán', 'hidden' => 'Ẩn khỏi web'])->default('active')->required(),
                TextInput::make('image')->label('Link ảnh đại diện')->url()->columnSpanFull()->helperText('Tạm thời dán link ảnh. Chức năng tải ảnh lên sẽ có ở bước sau.'),
                RichEditor::make('description')->label('Mô tả chung (dùng khi quy cách không có mô tả riêng)')->columnSpanFull(),
            ]),
            Section::make('Quy cách')->columnSpanFull()->schema([
                Repeater::make('variants')->hiddenLabel()->relationship()->orderColumn('sort_order')->reorderable()
                    ->itemLabel(fn (array $state) => ($state['name'] ?? 'Quy cách mới') . (isset($state['list_price']) ? ' · ' . OrderResource::vnd($state['list_price']) : ''))
                    ->collapsible()->collapsed()->columns(4)->addActionLabel('Thêm quy cách')
                    ->schema([
                        TextInput::make('name')->label('Tên quy cách')->required()->columnSpan(2),
                        TextInput::make('sku')->label('SKU')->maxLength(64)->unique(ignoreRecord: true),
                        TextInput::make('list_price')->label('Giá niêm yết (đ)')->numeric()->minValue(0)->required(),
                        TextInput::make('compare_price')->label('Giá gạch (đ)')->numeric()->minValue(0),
                        TextInput::make('stock_qty')->label('Tồn kho')->numeric()->helperText('Đổi tồn nên dùng Nhập/Xuất kho để có lịch sử'),
                        TextInput::make('weight_grams')->label('Khối lượng (g)')->numeric()->minValue(0),
                        Toggle::make('is_available')->label('Còn bán')->default(true)->inline(false),
                        RichEditor::make('description')->label('Mô tả riêng của quy cách này')->columnSpanFull(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['variants', 'category', 'brand']))
            ->columns([
                ImageColumn::make('image')->label('')->getStateUsing(fn ($record) => $record->image ? (str_starts_with($record->image, '//') ? 'https:' . $record->image : $record->image) : null)->square()->size(44),
                TextColumn::make('name')->label('Sản phẩm')->searchable()->sortable()->weight('bold')->description(fn ($record) => $record->brand?->name),
                TextColumn::make('category.name')->label('Nhóm')->sortable(),
                TextColumn::make('variants_count')->label('Quy cách')->counts('variants')->alignCenter(),
                TextColumn::make('price_range')->label('Giá')->alignEnd()->getStateUsing(function ($record) {
                    $p = $record->variants->pluck('list_price'); if ($p->isEmpty()) return '—';
                    return $p->min() === $p->max() ? OrderResource::vnd($p->min()) : OrderResource::vnd($p->min()) . ' – ' . OrderResource::vnd($p->max());
                }),
                TextColumn::make('stock_state')->label('Tình trạng')->badge()->getStateUsing(function ($record) {
                    $out = $record->variants->where('is_available', false)->count();
                    return $record->status !== 'active' ? 'Ẩn' : ($out === $record->variants->count() ? 'Hết hàng' : ($out ? "Hết {$out} quy cách" : 'Đang bán'));
                })->color(fn ($state) => $state === 'Đang bán' ? 'success' : ($state === 'Ẩn' ? 'gray' : 'warning')),
                TextColumn::make('temp_sku')->label('Mã tạm')->badge()->color('warning')->placeholder('')
                    ->getStateUsing(fn ($record) => ($n = $record->variants->filter(fn ($v) => str_starts_with((string) $v->sku, 'YSNT-'))->count()) ? "$n mã tạm" : null),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Nhóm')->relationship('category', 'name'),
                SelectFilter::make('brand_id')->label('Nhãn hàng')->relationship('brand', 'name'),
                SelectFilter::make('status')->label('Trạng thái')->options(['active' => 'Đang bán', 'hidden' => 'Ẩn']),
            ])
            ->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListProducts::route('/'), 'create' => CreateProduct::route('/create'), 'edit' => EditProduct::route('/{record}/edit')];
    }
}
