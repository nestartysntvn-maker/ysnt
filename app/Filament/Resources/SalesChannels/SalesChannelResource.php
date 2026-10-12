<?php
namespace App\Filament\Resources\SalesChannels;

use App\Channels\ChannelSync;
use App\Filament\Resources\SalesChannels\Pages\CreateSalesChannel;
use App\Filament\Resources\SalesChannels\Pages\EditSalesChannel;
use App\Filament\Resources\SalesChannels\Pages\ListSalesChannels;
use App\Jobs\SyncChannel;
use App\Models\SalesChannel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SalesChannelResource extends Resource
{
    protected static ?string $model = SalesChannel::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;
    protected static string|UnitEnum|null $navigationGroup = 'Kết nối sàn';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'gian hàng trên sàn';
    protected static ?string $pluralModelLabel = 'Gian hàng trên sàn';
    protected static ?string $navigationLabel = 'Gian hàng trên sàn';
    public const SECRETS = ['app_secret', 'access_token', 'refresh_token'];   // never sent back to the browser
    public const STATUS = ['draft' => 'Chưa kết nối', 'active' => 'Đang chạy', 'paused' => 'Tạm dừng', 'error' => 'Lỗi'];

    public static function canViewAny(): bool { return auth()->user()?->role === 'admin'; }

    public static function form(Schema $schema): Schema
    {
        $secret = fn (string $k, string $label) => TextInput::make("credentials.$k")->label($label)->password()->revealable()
            ->placeholder(fn ($record) => ! empty($record?->credentials[$k]) ? 'Đã lưu. Để trống nếu không đổi' : null);
        return $schema->components([
            Section::make('Gian hàng')->columns(3)->columnSpanFull()->schema([
                Select::make('platform')->label('Sàn')->options(ChannelSync::PLATFORMS)->required()->disabledOn('edit')->live(),
                TextInput::make('name')->label('Tên gian hàng')->required(),
                TextInput::make('shop_id')->label('Shop ID trên sàn'),
                Select::make('status')->label('Trạng thái')->options(self::STATUS)->default('draft')->required(),
                Toggle::make('sync_orders')->label('Tự kéo đơn về')->default(true)->inline(false),
                Toggle::make('sync_stock')->label('Tự đẩy tồn kho lên sàn')->default(false)->inline(false)->helperText('Chỉ bật khi kho trên web đã chuẩn'),
            ]),
            Section::make('Thông tin ứng dụng (mã hóa khi lưu)')->description('Lấy từ cổng nhà phát triển của sàn sau khi ứng dụng được duyệt. Lưu xong bấm "Cấp quyền gian hàng": token tự lấy và tự gia hạn.')->columns(2)->columnSpanFull()->schema([
                TextInput::make('credentials.app_key')->label('App key (TikTok, Lazada) / Partner ID (Shopee)'),
                $secret('app_secret', 'App secret / Partner key'),
                TextInput::make('credentials.service_id')->label('Service ID (TikTok Shop)')->visible(fn ($get) => $get('platform') === 'tiktok'),
                Toggle::make('credentials.sandbox')->label('Dùng môi trường thử của Shopee')->inline(false)->visible(fn ($get) => $get('platform') === 'shopee'),
                Toggle::make('credentials.sync_chat')->label('Kéo tin nhắn khách về hộp thư chung')->inline(false)->visible(fn ($get) => $get('platform') === 'tiktok'),
                Placeholder::make('callback')->label('Đường dẫn callback (khai báo với sàn)')
                    ->content(fn ($get) => $get('platform') ? \App\Http\Controllers\MarketplaceAuthController::callbackUrl($get('platform')) : 'Chọn sàn trước'),
                $secret('access_token', 'Access token (tự điền khi cấp quyền)'),
                $secret('refresh_token', 'Refresh token (tự điền khi cấp quyền)'),
            ]),
            Section::make('Tình trạng đồng bộ')->columnSpanFull()->visibleOn('edit')->schema([
                Placeholder::make('st')->hiddenLabel()->content(fn ($record) => new HtmlString($record ? 'Kéo đơn gần nhất: ' . ($record->last_order_sync_at?->format('H:i d/m/Y') ?? 'chưa') . '<br>Đẩy tồn gần nhất: ' . ($record->last_stock_sync_at?->format('H:i d/m/Y') ?? 'chưa')
                    . ($record->last_error ? '<br><b>Lỗi gần nhất:</b> ' . e($record->last_error) : '')
                    . '<br><br>' . $record->logs()->take(10)->get()->map(fn ($l) => e($l->created_at->format('H:i d/m')) . ' · ' . e($l->kind) . ' · ' . e($l->status) . ' · ' . $l->items . ($l->message ? ' · ' . e(mb_strimwidth($l->message, 0, 160, '…')) : ''))->implode('<br>') : '')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Gian hàng')->weight('bold')->description(fn ($record) => ChannelSync::PLATFORMS[$record->platform] ?? $record->platform),
            TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => self::STATUS[$state] ?? $state)
                ->color(fn ($state) => match ($state) { 'active' => 'success', 'error' => 'danger', 'paused' => 'gray', default => 'warning' }),
            TextColumn::make('listings_count')->label('SP đã ghép')->counts('listings')->alignCenter(),
            IconColumn::make('sync_orders')->label('Kéo đơn')->boolean(),
            IconColumn::make('sync_stock')->label('Đẩy tồn')->boolean(),
            TextColumn::make('last_order_sync_at')->label('Kéo đơn lần cuối')->since()->placeholder('Chưa'),
            TextColumn::make('last_error')->label('Lỗi gần nhất')->limit(50)->placeholder('—'),
        ])->recordActions([
            Action::make('authorize')->label('Cấp quyền gian hàng')->icon(Heroicon::OutlinedKey)->url(fn (SalesChannel $record) => url('/ket-noi-san/' . $record->id . '/cap-quyen')),
            Action::make('ping')->label('Kiểm tra kết nối')->icon(Heroicon::OutlinedSignal)->action(function (SalesChannel $record) {
                $m = \App\Channels\Connectors\Marketplace::make($record->platform);
                if (! $m || ! \App\Channels\Connectors\Marketplace::ready($record)) { Notification::make()->title('Chưa cấp quyền gian hàng')->body('Nhập app key, app secret, lưu, rồi bấm Cấp quyền gian hàng.')->warning()->send(); return; }
                try { Notification::make()->title($m->ping($record))->success()->send(); } catch (\Throwable $e) { Notification::make()->title('Sàn báo lỗi')->body($e->getMessage())->danger()->send(); }
            }),
            Action::make('sync')->label('Đồng bộ ngay')->icon(Heroicon::OutlinedArrowPath)
                ->action(function (SalesChannel $record) { SyncChannel::dispatch($record->id); Notification::make()->title('Đã đưa vào hàng đợi đồng bộ')->success()->send(); }),
            EditAction::make()->label('Sửa'),
        ])->emptyStateHeading('Chưa có gian hàng nào')->emptyStateDescription('Thêm gian hàng TikTok Shop, Shopee, Lazada. Khi sàn duyệt ứng dụng, dán thông tin vào là chạy.');
    }

    public static function getPages(): array { return ['index' => ListSalesChannels::route('/'), 'create' => CreateSalesChannel::route('/create'), 'edit' => EditSalesChannel::route('/{record}/edit')]; }
}
