<?php
namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;
    protected static string|UnitEnum|null $navigationGroup = 'Bán hàng';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'đơn hàng';
    protected static ?string $pluralModelLabel = 'Đơn hàng';
    protected static ?string $recordTitleAttribute = 'code';

    public const STATUS = ['new' => 'Mới', 'confirmed' => 'Đã xác nhận', 'shipping' => 'Đang giao', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', 'returned' => 'Hoàn hàng'];
    public const CHANNEL = ['web' => 'Web', 'showroom' => 'Showroom', 'tiktok' => 'TikTok Shop', 'shopee' => 'Shopee', 'lazada' => 'Lazada', 'agent' => 'Đại lý', 'phone' => 'Điện thoại'];
    public const PAY = ['cod' => 'Trả khi nhận hàng', 'bank' => 'Chuyển khoản', 'vnpay' => 'VNPay, MoMo', 'cash' => 'Tiền mặt', 'card' => 'Quẹt thẻ'];
    public const PAY_STATUS = ['unpaid' => 'Chưa thu', 'paid' => 'Đã thu', 'refunded' => 'Đã hoàn tiền'];

    public static function vnd($v): string { return number_format((int) $v, 0, ',', '.') . 'đ'; }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::where('status', 'new')->count();
        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Xử lý đơn')->columns(3)->columnSpanFull()->schema([
                Select::make('status')->label('Trạng thái')->options(self::STATUS)->required(),
                Select::make('payment_status')->label('Thanh toán')->options(self::PAY_STATUS)->required(),
                TextInput::make('shipping_fee')->label('Phí giao hàng (đ)')->numeric()->minValue(0)->default(0),
                Textarea::make('internal_note')->label('Ghi chú nội bộ (khách không thấy)')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Thông tin đơn')->columns(3)->columnSpanFull()->schema([
                Placeholder::make('code_v')->label('Mã đơn')->content(fn ($record) => $record?->code),
                Placeholder::make('channel_v')->label('Kênh')->content(fn ($record) => self::CHANNEL[$record?->channel] ?? $record?->channel),
                Placeholder::make('created_v')->label('Đặt lúc')->content(fn ($record) => $record?->created_at?->format('H:i d/m/Y')),
                Placeholder::make('ship_v')->label('Người nhận')->content(fn ($record) => $record ? "{$record->ship_name} · {$record->ship_phone}" : ''),
                Placeholder::make('addr_v')->label('Địa chỉ')->content(fn ($record) => $record?->ship_address)->columnSpan(2),
                Placeholder::make('ref_v')->label('Mã giới thiệu')->content(fn ($record) => $record?->ref_code ?: '—'),
                Placeholder::make('pay_v')->label('Cách thanh toán')->content(fn ($record) => self::PAY[$record?->payment_method] ?? $record?->payment_method),
                Placeholder::make('note_v')->label('Khách ghi chú')->content(fn ($record) => $record?->note ?: '—'),
            ]),
            Section::make('Sản phẩm')->columnSpanFull()->schema([
                Placeholder::make('items_v')->hiddenLabel()->content(fn ($record) => new HtmlString($record ? self::itemsTable($record) : '')),
            ]),
            Section::make('Lịch sử trạng thái')->collapsed()->columnSpanFull()->schema([
                Placeholder::make('log_v')->hiddenLabel()->content(fn ($record) => new HtmlString($record ? ($record->statusLogs->map(fn ($l) => e($l->created_at?->format('H:i d/m/Y')) . ': ' . e(self::STATUS[$l->from_status] ?? '—') . ' → <b>' . e(self::STATUS[$l->to_status] ?? $l->to_status) . '</b> · ' . e($l->user?->name ?? 'Hệ thống') . ($l->note ? ' · ' . e($l->note) : ''))->implode('<br>') ?: 'Chưa có thay đổi.') : '')),
            ]),
        ]);
    }

    public static function itemsTable(Order $o): string
    {
        $td = 'style="padding:6px 8px;border-bottom:1px solid rgba(0,0,0,.06)"';
        $rows = $o->items->map(fn ($i) => "<tr><td $td>" . e($i->product_name) . '<br><small style="opacity:.7">' . e($i->variant_name) . ' · ' . e($i->sku) . "</small></td><td $td align=right>" . self::vnd($i->unit_price) . "</td><td $td align=center>{$i->qty}</td><td $td align=right><b>" . self::vnd($i->line_total) . '</b></td></tr>')->implode('');
        $sum = "<tr><td colspan=3 align=right style=\"padding:6px 8px\">Tạm tính</td><td align=right style=\"padding:6px 8px\">" . self::vnd($o->subtotal) . '</td></tr>'
            . ($o->discount ? "<tr><td colspan=3 align=right style=\"padding:6px 8px\">Giảm giá</td><td align=right style=\"padding:6px 8px\">−" . self::vnd($o->discount) . '</td></tr>' : '')
            . "<tr><td colspan=3 align=right style=\"padding:6px 8px\"><b>Tổng tiền hàng</b></td><td align=right style=\"padding:6px 8px\"><b>" . self::vnd($o->total) . '</b></td></tr>';
        return '<table style="width:100%;border-collapse:collapse;font-size:14px"><thead><tr style="opacity:.7;text-align:left"><th style="padding:6px 8px">Sản phẩm</th><th style="padding:6px 8px;text-align:right">Đơn giá</th><th style="padding:6px 8px">SL</th><th style="padding:6px 8px;text-align:right">Thành tiền</th></tr></thead><tbody>' . $rows . $sum . '</tbody></table>';
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Mã đơn')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Đặt lúc')->dateTime('H:i d/m/Y')->sortable(),
                TextColumn::make('ship_name')->label('Khách')->description(fn ($record) => $record->ship_phone)->searchable(['ship_name', 'ship_phone']),
                TextColumn::make('channel')->label('Kênh')->badge()->formatStateUsing(fn ($state) => self::CHANNEL[$state] ?? $state),
                TextColumn::make('total')->label('Tổng tiền')->formatStateUsing(fn ($state) => self::vnd($state))->alignEnd()->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => self::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'new' => 'warning', 'confirmed', 'shipping' => 'info', 'completed' => 'success', default => 'danger' }),
                TextColumn::make('payment_status')->label('Thanh toán')->badge()->formatStateUsing(fn ($state) => self::PAY_STATUS[$state] ?? $state)->color(fn ($state) => $state === 'paid' ? 'success' : 'gray'),
                TextColumn::make('ref_code')->label('Mã giới thiệu')->placeholder('—')->searchable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(self::STATUS),
                SelectFilter::make('channel')->label('Kênh')->options(self::CHANNEL),
                SelectFilter::make('payment_status')->label('Thanh toán')->options(self::PAY_STATUS),
            ])
            ->recordActions([EditAction::make()->label('Xử lý')])
            ->emptyStateHeading('Chưa có đơn hàng')->emptyStateDescription('Đơn từ web, showroom và các sàn sẽ hiện ở đây.');
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/'), 'edit' => EditOrder::route('/{record}/edit')];
    }
}
