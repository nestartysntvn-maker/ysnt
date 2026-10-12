<?php
namespace App\Filament\Resources\ZaloTemplates;

use App\Filament\Resources\ZaloTemplates\Pages\EditZaloTemplate;
use App\Filament\Resources\ZaloTemplates\Pages\ListZaloTemplates;
use App\Models\ZaloTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
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

class ZaloTemplateResource extends Resource
{
    protected static ?string $model = ZaloTemplate::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
    protected static string|UnitEnum|null $navigationGroup = 'Zalo OA';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'mẫu tin ZNS';
    protected static ?string $pluralModelLabel = 'Mẫu tin ZNS';
    protected static ?string $navigationLabel = 'Mẫu tin ZNS';
    public const TRIGGERS = ['order_created' => 'Ngay khi đặt đơn', 'order_shipping' => 'Đơn chuyển "Đang giao"', 'completed_1d' => '1 ngày sau khi hoàn thành',
        'completed_7d' => '7 ngày sau khi hoàn thành', 'reorder' => 'Theo quy cách đã mua', 'occasion' => '7 ngày trước dịp khách lưu', 'gift' => 'Khi có người tặng quà'];
    public const LIVE = ['order_created', 'order_shipping'];

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'content'], true); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->columns(2)->columnSpanFull()->schema([
            TextInput::make('name')->label('Tên mẫu')->required(),
            TextInput::make('zns_template_id')->label('Mã mẫu Zalo cấp')->placeholder('Để trống cho đến khi Zalo duyệt')->helperText('Có mã này và đã kết nối OA thì hệ thống gửi thật'),
            Textarea::make('sample_text')->label('Nội dung mẫu (gửi Zalo duyệt)')->rows(4)->columnSpanFull(),
            KeyValue::make('params')->label('Ghép dữ liệu vào mẫu')->keyLabel('Tham số trong mẫu Zalo')->valueLabel('Lấy từ')->columnSpanFull()
                ->helperText('Giá trị dùng được: order.code, order.customer_name, order.total_vnd, order.date, order.items, order.first_item, order.track_url, hoặc text:nội dung cố định'),
            Toggle::make('is_active')->label('Bật mẫu này')->inline(false)
                ->helperText(fn ($record) => $record && ! in_array($record->trigger, self::LIVE, true) ? 'Mẫu này chưa nối vào luồng tự động (chờ tính năng tương ứng)' : null),
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->paginated(false)->columns([
            TextColumn::make('name')->label('Mẫu')->weight('bold')->description(fn ($record) => $record->code),
            TextColumn::make('trigger')->label('Gửi khi')->formatStateUsing(fn ($state) => self::TRIGGERS[$state] ?? $state),
            TextColumn::make('zns_template_id')->label('Mã mẫu Zalo')->placeholder('Chờ duyệt')->badge()->color(fn ($state) => $state ? 'success' : 'warning'),
            TextColumn::make('wired')->label('Luồng tự động')->badge()->getStateUsing(fn ($record) => in_array($record->trigger, self::LIVE, true) ? 'Đã nối' : 'Chưa nối')
                ->color(fn ($state) => $state === 'Đã nối' ? 'success' : 'gray'),
            IconColumn::make('is_active')->label('Bật')->boolean(),
            TextColumn::make('messages_count')->label('Đã ghi')->counts('messages')->alignCenter(),
        ])->recordActions([EditAction::make()->label('Sửa')]);
    }

    public static function getPages(): array { return ['index' => ListZaloTemplates::route('/'), 'edit' => EditZaloTemplate::route('/{record}/edit')]; }
}
