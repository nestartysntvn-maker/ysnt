<?php
namespace App\Filament\Resources\CommissionPolicies;

use App\Filament\Resources\Ambassadors\AmbassadorResource;
use App\Filament\Resources\CommissionPolicies\Pages\EditCommissionPolicy;
use App\Filament\Resources\CommissionPolicies\Pages\ListCommissionPolicies;
use App\Models\CommissionPolicy;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CommissionPolicyResource extends Resource
{
    protected static ?string $model = CommissionPolicy::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static string|UnitEnum|null $navigationGroup = 'Đại sứ & hoa hồng';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'chính sách hoa hồng';
    protected static ?string $pluralModelLabel = 'Chính sách hoa hồng';
    protected static ?string $navigationLabel = 'Chính sách hoa hồng';

    public static function form(Schema $schema): Schema
    {
        $pct = fn (string $f, string $l) => TextInput::make($f)->label($l)->numeric()->minValue(0)->maxValue(100)->suffix('%')->placeholder('Chưa chốt');
        return $schema->components([
            Section::make(fn ($record) => $record?->name)->description('Để trống ô nào nghĩa là mức đó chưa chốt. Loại chưa có mức hoa hồng thì hệ thống không tính hoa hồng.')
                ->columns(3)->columnSpanFull()->schema([
                    TextInput::make('name')->label('Tên chính sách')->required()->columnSpan(3),
                    $pct('rate_percent', 'Hoa hồng'),
                    $pct('customer_discount_percent', 'Khách được giảm'),
                    $pct('upline_rate_percent', 'Hoa hồng đầu mối'),
                    $pct('bonus_rate_percent', 'Hoa hồng khi vượt ngưỡng'),
                    TextInput::make('bonus_threshold_amount')->label('Ngưỡng doanh số tháng')->numeric()->minValue(0)->suffix('đ')->placeholder('Chưa chốt'),
                    TextInput::make('note')->label('Ghi chú'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $p = fn ($state) => $state === null ? 'Chưa chốt' : rtrim(rtrim(number_format((float) $state, 2, ',', ''), '0'), ',') . '%';
        return $table->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Loại đại sứ')->weight('bold')->description(fn ($record) => AmbassadorResource::TYPES[$record->ambassador_type] ?? $record->ambassador_type),
                TextColumn::make('rate_percent')->label('Hoa hồng')->formatStateUsing($p)->placeholder('Chưa chốt')->badge()->color(fn ($state) => $state === null ? 'warning' : 'success'),
                TextColumn::make('bonus_rate_percent')->label('Vượt ngưỡng')->formatStateUsing($p)->placeholder('—'),
                TextColumn::make('customer_discount_percent')->label('Khách giảm')->formatStateUsing($p)->placeholder('—'),
                TextColumn::make('upline_rate_percent')->label('Đầu mối')->formatStateUsing($p)->placeholder('Chưa chốt'),
                TextColumn::make('note')->label('Ghi chú')->placeholder('')->wrap(),
            ])
            ->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCommissionPolicies::route('/'), 'edit' => EditCommissionPolicy::route('/{record}/edit')];
    }
}
