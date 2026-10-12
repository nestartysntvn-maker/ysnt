<?php
namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Hr\Hr;
use App\Models\Department;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'phòng ban';
    protected static ?string $pluralModelLabel = 'Phòng ban';

    public static function canViewAny(): bool { return Hr::can('staff'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Tên phòng ban')->required()->maxLength(64)->unique(ignoreRecord: true),
            Select::make('head_employee_id')->label('Trưởng phòng')->searchable()->options(fn () => Employee::where('status', 'active')->orderBy('full_name')->pluck('full_name', 'id')),
            TextInput::make('description')->label('Mô tả')->maxLength(255),
            TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
            Toggle::make('is_active')->label('Đang dùng')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('name')->label('Phòng ban')->weight('bold')->description(fn ($record) => $record->description),
            TextColumn::make('head.full_name')->label('Trưởng phòng')->placeholder('—'),
            TextColumn::make('employees_count')->label('Nhân viên đang làm')->counts(['employees' => fn ($q) => $q->where('status', 'active')])->alignCenter(),
            IconColumn::make('is_active')->label('Đang dùng')->boolean(),
        ])->recordActions([EditAction::make()->label('Sửa')])->paginated(false);
    }

    public static function getPages(): array { return ['index' => ManageDepartments::route('/')]; }
}
