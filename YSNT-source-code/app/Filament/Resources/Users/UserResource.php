<?php
namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
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
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;
    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'nhân viên';
    protected static ?string $pluralModelLabel = 'Nhân viên, phân quyền';
    protected static ?string $navigationLabel = 'Nhân viên, phân quyền';

    public static function canViewAny(): bool { return auth()->user()?->role === 'admin'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->columns(2)->columnSpanFull()->schema([
            TextInput::make('name')->label('Họ tên')->required(),
            TextInput::make('email')->label('Email đăng nhập')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Số điện thoại'),
            Select::make('role')->label('Vai trò')->options(User::ROLES)->required()->default('sales'),
            TextInput::make('password')->label('Mật khẩu')->password()->revealable()->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Để trống nếu không đổi mật khẩu.' : null),
            Toggle::make('is_active')->label('Đang làm việc (cho phép đăng nhập)')->default(true)->inline(false),
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nhân viên')->weight('bold')->description(fn ($record) => $record->email)->searchable(['name', 'email']),
            TextColumn::make('role')->label('Vai trò')->badge()->formatStateUsing(fn ($state) => User::ROLES[$state] ?? $state),
            TextColumn::make('phone')->label('SĐT')->placeholder('—'),
            IconColumn::make('is_active')->label('Đăng nhập được')->boolean(),
        ])->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array { return ['index' => ListUsers::route('/'), 'create' => CreateUser::route('/create'), 'edit' => EditUser::route('/{record}/edit')]; }
}
