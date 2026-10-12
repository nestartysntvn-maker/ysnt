<?php
namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** old URLs (ysnt.com.vn after the domain moves here) -> new pages, so Google rankings and shared links survive */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;
    protected static string|UnitEnum|null $navigationGroup = 'SEO';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'chuyển hướng';
    protected static ?string $pluralModelLabel = 'Chuyển hướng link cũ';
    protected static ?string $navigationLabel = 'Chuyển hướng link cũ';

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'content'], true); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('Link cũ')->required()->maxLength(500)->placeholder('/san-pham/to-yen-cu/')->helperText('Chỉ phần sau tên miền.')
                ->dehydrateStateUsing(fn ($state) => Redirect::normalize((string) $state))->unique(ignoreRecord: true),
            TextInput::make('to_path')->label('Chuyển tới')->required()->maxLength(500)->placeholder('/san-pham/to-yen-moi'),
            Select::make('code')->label('Loại')->options([301 => '301 · chuyển hẳn (giữ thứ hạng Google)', 302 => '302 · tạm thời'])->default(301)->required(),
            TextInput::make('note')->label('Ghi chú')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('from_path')->label('Link cũ')->searchable()->wrap(),
            TextColumn::make('to_path')->label('Chuyển tới')->wrap(),
            TextColumn::make('code')->label('Loại')->badge(),
            TextColumn::make('hits')->label('Lượt')->alignCenter()->sortable(),
            TextColumn::make('last_hit_at')->label('Lần cuối')->since()->placeholder('—'),
        ])->recordActions([EditAction::make()->label('Sửa'), DeleteAction::make()->label('Xóa')])
            ->emptyStateHeading('Chưa có chuyển hướng')->emptyStateDescription('Khi đưa tên miền cũ về hệ thống này, thêm link cũ để khách và Google không gặp trang lỗi.');
    }

    public static function getPages(): array { return ['index' => ManageRedirects::route('/')]; }
}
