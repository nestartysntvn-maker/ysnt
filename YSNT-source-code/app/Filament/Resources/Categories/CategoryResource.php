<?php
namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
    protected static string|UnitEnum|null $navigationGroup = 'Sản phẩm';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'danh mục';
    protected static ?string $pluralModelLabel = 'Danh mục';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->columns(2)->columnSpanFull()->schema([
            TextInput::make('name')->label('Tên danh mục')->required()->live(onBlur: true)->afterStateUpdated(fn ($state, $set, $record) => $record ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->label('Đường dẫn')->required()->unique(ignoreRecord: true),
            TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
            Toggle::make('is_active')->label('Hiện trên web')->default(true)->inline(false),
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->reorderable('sort_order')->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Danh mục')->weight('bold')->description(fn ($record) => '/danh-muc/' . $record->slug),
                TextColumn::make('products_count')->label('Sản phẩm')->counts('products')->alignCenter(),
                IconColumn::make('is_active')->label('Hiện trên web')->boolean(),
            ])->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCategories::route('/'), 'create' => CreateCategory::route('/create'), 'edit' => EditCategory::route('/{record}/edit')];
    }
}
