<?php
namespace App\Filament\Resources\PayoutRequests;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PayoutRequests\Pages\CreatePayoutRequest;
use App\Filament\Resources\PayoutRequests\Pages\ListPayoutRequests;
use App\Models\Commission;
use App\Models\PayoutRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;
    protected static string|UnitEnum|null $navigationGroup = 'Đại sứ & hoa hồng';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'yêu cầu rút tiền';
    protected static ?string $pluralModelLabel = 'Rút tiền hoa hồng';
    protected static ?string $navigationLabel = 'Rút tiền';

    public static function available(int $ambassadorId): int
    {
        $approved = Commission::where('ambassador_id', $ambassadorId)->where('status', 'approved')->sum('amount');
        $pending = PayoutRequest::where('ambassador_id', $ambassadorId)->where('status', 'pending')->sum('amount');
        return (int) ($approved - $pending);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('ambassador_id')->label('Đại sứ')->relationship('ambassador', 'name')->searchable()->preload()->required()->live()
                    ->helperText(fn ($get) => $get('ambassador_id') ? 'Hoa hồng đã duyệt còn rút được: ' . OrderResource::vnd(self::available((int) $get('ambassador_id'))) : null),
                TextInput::make('amount')->label('Số tiền (đ)')->numeric()->minValue(1)->required()
                    ->rule(fn ($get) => function ($attr, $value, $fail) use ($get) {
                        if ($get('ambassador_id') && $value > self::available((int) $get('ambassador_id'))) $fail('Vượt số hoa hồng đã duyệt còn rút được.');
                    }),
                TextInput::make('note')->label('Ghi chú')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ambassador.name')->label('Đại sứ')->description(fn ($record) => $record->ambassador?->ref_code)->searchable(),
                TextColumn::make('amount')->label('Số tiền')->formatStateUsing(fn ($state) => OrderResource::vnd($state))->alignEnd()->weight('bold'),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => ['pending' => 'Chờ trả', 'paid' => 'Đã trả', 'rejected' => 'Từ chối'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'paid' => 'success', 'rejected' => 'danger', default => 'warning' }),
                TextColumn::make('created_at')->label('Ngày yêu cầu')->date('d/m/Y'),
                TextColumn::make('paid_at')->label('Ngày trả')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('note')->label('Ghi chú')->placeholder('')->limit(40),
            ])
            ->recordActions([
                Action::make('pay')->label('Đã chuyển tiền')->color('success')->requiresConfirmation()
                    ->modalDescription('Đánh dấu đã trả. Hoa hồng đã duyệt của đại sứ sẽ chuyển sang "Đã trả" theo đúng số tiền này.')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function (PayoutRequest $record) {
                        DB::transaction(function () use ($record) {
                            $left = (int) $record->amount;
                            foreach (Commission::where('ambassador_id', $record->ambassador_id)->where('status', 'approved')->orderBy('id')->lockForUpdate()->get() as $c) {
                                if ($left < $c->amount) break;
                                $c->update(['status' => 'paid', 'paid_at' => now()]); $left -= $c->amount;
                            }
                            $record->update(['status' => 'paid', 'paid_at' => now(), 'note' => trim(($record->note ? $record->note . '. ' : '') . ($left > 0 ? 'Còn ' . OrderResource::vnd($left) . ' chưa khớp đúng dòng hoa hồng, cần đối soát' : ''))]);
                        });
                    }),
                Action::make('reject')->label('Từ chối')->color('danger')->requiresConfirmation()->visible(fn ($record) => $record->status === 'pending')
                    ->action(fn (PayoutRequest $record) => $record->update(['status' => 'rejected'])),
            ])
            ->emptyStateHeading('Chưa có yêu cầu rút tiền');
    }

    public static function getPages(): array { return ['index' => ListPayoutRequests::route('/'), 'create' => CreatePayoutRequest::route('/create')]; }
}
