<?php
namespace App\Filament\Resources\TourVisits;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\TourVisits\Pages\CreateTourVisit;
use App\Filament\Resources\TourVisits\Pages\EditTourVisit;
use App\Filament\Resources\TourVisits\Pages\ListTourVisits;
use App\Models\Order;
use App\Models\TourVisit;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TourVisitResource extends Resource
{
    protected static ?string $model = TourVisit::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static string|UnitEnum|null $navigationGroup = 'Bán hàng';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'lượt xe tour';
    protected static ?string $pluralModelLabel = 'Xe tour ghé';
    protected static ?string $navigationLabel = 'Xe tour ghé';

    public const MARKETS = ['vn' => 'Việt Nam', 'cn' => 'Trung Quốc', 'kr' => 'Hàn Quốc', 'ru' => 'Nga', 'other' => 'Khác'];
    public const WINDOW_HOURS = 3;

    /** Orders at the showroom under this guide's code within 3 hours of arrival. */
    public static function sales(TourVisit $v): array
    {
        if (! $v->ambassador_id) return ['n' => null, 'sum' => null];
        $q = Order::where('channel', 'showroom')->where('ambassador_id', $v->ambassador_id)->whereNotIn('status', ['cancelled', 'returned'])
            ->whereBetween('created_at', [$v->visited_at, $v->visited_at->copy()->addHours(self::WINDOW_HOURS)]);
        return ['n' => (clone $q)->count(), 'sum' => (int) $q->sum('total')];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->columns(2)->columnSpanFull()->schema([
            DateTimePicker::make('visited_at')->label('Giờ xe đến')->seconds(false)->default(now())->required(),
            TextInput::make('pax')->label('Số khách trên xe')->numeric()->minValue(1)->maxValue(80)->required(),
            TextInput::make('operator_name')->label('Công ty lữ hành')->required()
                ->datalist(fn () => TourVisit::query()->distinct()->orderBy('operator_name')->limit(100)->pluck('operator_name')->all()),
            Select::make('market')->label('Khách từ')->options(self::MARKETS)->default('vn')->required(),
            Select::make('ambassador_id')->label('HDV, tài xế có mã')->relationship('ambassador', 'name', fn ($query) => $query->whereIn('type', ['guide', 'guide_regular']))
                ->searchable()->preload()->helperText('Chọn để tự tính doanh thu của xe'),
            TextInput::make('guide_name')->label('Tên HDV (nếu chưa có mã)'),
            TextInput::make('plate')->label('Biển số xe'),
            TextInput::make('note')->label('Ghi chú'),
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('visited_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with('ambassador'))
            ->columns([
                TextColumn::make('visited_at')->label('Giờ đến')->dateTime('H:i d/m')->sortable(),
                TextColumn::make('operator_name')->label('Công ty lữ hành')->searchable()->description(fn ($record) => $record->plate),
                TextColumn::make('market')->label('Khách')->badge()->formatStateUsing(fn ($state) => self::MARKETS[$state] ?? $state),
                TextColumn::make('pax')->label('Số khách')->alignCenter(),
                TextColumn::make('guide')->label('HDV')->getStateUsing(fn ($record) => $record->ambassador ? $record->ambassador->name . ' · ' . $record->ambassador->ref_code : ($record->guide_name ?: '—')),
                TextColumn::make('orders')->label('Đơn')->alignCenter()->getStateUsing(fn ($record) => self::sales($record)['n'] ?? 'Chưa có mã'),
                TextColumn::make('revenue')->label('Doanh thu')->alignEnd()->getStateUsing(fn ($record) => ($s = self::sales($record)['sum']) === null ? '—' : OrderResource::vnd($s)),
                TextColumn::make('per_pax')->label('Mỗi khách')->alignEnd()
                    ->getStateUsing(fn ($record) => ($s = self::sales($record)['sum']) === null || ! $record->pax ? '—' : OrderResource::vnd(intdiv($s, $record->pax))),
                TextColumn::make('buy_rate')->label('Tỷ lệ mua')->alignEnd()
                    ->getStateUsing(fn ($record) => ($n = self::sales($record)['n']) === null || ! $record->pax ? '—' : round(min($n, $record->pax) / $record->pax * 100) . '%'),
            ])
            ->filters([
                Filter::make('today')->label('Hôm nay')->query(fn ($query) => $query->whereDate('visited_at', today())),
                SelectFilter::make('market')->label('Khách từ')->options(self::MARKETS),
            ])
            ->recordActions([EditAction::make()->label('Sửa')])
            ->emptyStateHeading('Chưa ghi nhận xe nào')->emptyStateDescription('Mỗi xe ghé, bấm "Tạo lượt xe tour" để ghi. Chọn HDV có mã để hệ thống tự tính doanh thu của xe.');
    }

    public static function getPages(): array { return ['index' => ListTourVisits::route('/'), 'create' => CreateTourVisit::route('/create'), 'edit' => EditTourVisit::route('/{record}/edit')]; }
}
