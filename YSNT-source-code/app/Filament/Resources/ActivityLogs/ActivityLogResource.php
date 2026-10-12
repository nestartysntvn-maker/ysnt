<?php
namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'nhật ký';
    protected static ?string $pluralModelLabel = 'Nhật ký thao tác';
    protected static ?string $navigationLabel = 'Nhật ký thao tác';

    public const SUBJECTS = ['Product' => 'Sản phẩm', 'ProductVariant' => 'Quy cách', 'Order' => 'Đơn hàng', 'CommissionPolicy' => 'Chính sách hoa hồng', 'Ambassador' => 'Đại sứ', 'Commission' => 'Hoa hồng', 'PayoutRequest' => 'Rút tiền', 'User' => 'Nhân viên', 'Category' => 'Danh mục', 'Customer' => 'Khách hàng', 'CustomerImportBatch' => 'Lô nhập khách'];
    public const ACTIONS = ['created' => 'Tạo', 'updated' => 'Sửa', 'deleted' => 'Xóa', 'export' => 'Xuất file', 'import' => 'Nhập danh sách', 'import_undo' => 'Hoàn tác nhập'];

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'accountant'], true); }
    public static function form(Schema $schema): Schema { return $schema; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('H:i:s d/m/Y')->sortable(),
                TextColumn::make('user.name')->label('Người làm')->placeholder('Hệ thống, khách đặt web'),
                TextColumn::make('action')->label('Thao tác')->badge()->formatStateUsing(fn ($state) => self::ACTIONS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'deleted', 'import_undo' => 'danger', 'created', 'import' => 'success', 'export' => 'warning', default => 'info' }),
                TextColumn::make('subject_type')->label('Đối tượng')->formatStateUsing(fn ($state, $record) => (self::SUBJECTS[$state] ?? $state) . ($record->subject_id ? ' #' . $record->subject_id : '')),
                TextColumn::make('changes')->label('Thay đổi')->wrap()->formatStateUsing(function ($state) {
                    $c = is_array($state) ? $state : json_decode((string) $state, true);
                    if (! $c) return '';
                    return collect($c)->map(fn ($v, $k) => $k . ': ' . (is_array($v) ? (json_encode($v['old'] ?? null, JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($v['new'] ?? null, JSON_UNESCAPED_UNICODE)) : json_encode($v, JSON_UNESCAPED_UNICODE)))->take(6)->implode('; ');
                }),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('action')->label('Thao tác')->options(self::ACTIONS), SelectFilter::make('subject_type')->label('Đối tượng')->options(self::SUBJECTS), SelectFilter::make('user_id')->label('Người làm')->relationship('user', 'name')])
            ->emptyStateHeading('Chưa có thao tác nào được ghi');
    }

    public static function getPages(): array { return ['index' => ListActivityLogs::route('/')]; }
}
