<?php
namespace App\Filament\Resources\SalesTargets;

use App\Filament\Resources\SalesTargets\Pages\EditSalesTarget;
use App\Filament\Resources\SalesTargets\Pages\ListSalesTargets;
use App\Models\SalesTarget;
use App\Support\Targets;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class SalesTargetResource extends Resource
{
    protected static ?string $model = SalesTarget::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;
    protected static string|UnitEnum|null $navigationGroup = 'Báo cáo';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'chỉ tiêu';
    protected static ?string $pluralModelLabel = 'Chỉ tiêu theo tháng';
    protected static ?string $navigationLabel = 'Chỉ tiêu theo tháng';

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'accountant'], true); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make(fn ($record) => $record ? (Targets::GROUPS[$record->channel_group] ?? '') . ' · tháng ' . Carbon::parse($record->period)->format('m/Y') : '')
            ->columns(2)->columnSpanFull()->schema([
                TextInput::make('target_amount')->label('Chỉ tiêu cam kết (đồng)')->numeric()->minValue(0)->required(),
                TextInput::make('stretch_amount')->label('Chỉ tiêu phấn đấu (đồng)')->numeric()->minValue(0)->placeholder('Chưa chia theo tháng'),
                TextInput::make('note')->label('Ghi chú')->columnSpanFull(),
            ])]);
    }

    public static function table(Table $table): Table
    {
        $actual = function ($record) {
            $f = Carbon::parse($record->period)->startOfMonth();
            return Targets::actual($f, $f->copy()->addMonth())[$record->channel_group] ?? 0;
        };
        return $table->defaultSort('period')->paginated([12, 36])->defaultPaginationPageOption(36)
            ->columns([
                TextColumn::make('period')->label('Tháng')->date('m/Y')->sortable(),
                TextColumn::make('channel_group')->label('Nhóm')->formatStateUsing(fn ($state) => Targets::GROUPS[$state] ?? $state)->badge(),
                TextColumn::make('target_amount')->label('Chỉ tiêu')->formatStateUsing(fn ($state) => Targets::ty((int) $state))->alignEnd(),
                TextColumn::make('actual')->label('Thực tế')->getStateUsing($actual)->formatStateUsing(fn ($state) => Targets::ty((int) $state))->alignEnd(),
                TextColumn::make('pct')->label('Hoàn thành')->alignEnd()->badge()
                    ->getStateUsing(fn ($record) => $record->target_amount ? round($actual($record) / $record->target_amount * 100) . '%' : '—')
                    ->color(fn ($state) => (int) $state >= 100 ? 'success' : ((int) $state >= 70 ? 'warning' : 'gray')),
                TextColumn::make('stretch_amount')->label('Phấn đấu')->formatStateUsing(fn ($state) => Targets::ty((int) $state))->placeholder('Chưa chia')->alignEnd(),
            ])
            ->filters([SelectFilter::make('channel_group')->label('Nhóm')->options(Targets::GROUPS)])
            ->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array { return ['index' => ListSalesTargets::route('/'), 'edit' => EditSalesTarget::route('/{record}/edit')]; }
}
