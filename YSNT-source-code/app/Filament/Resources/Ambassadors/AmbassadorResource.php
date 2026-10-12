<?php
namespace App\Filament\Resources\Ambassadors;

use App\Filament\Resources\Ambassadors\Pages\CreateAmbassador;
use App\Filament\Resources\Ambassadors\Pages\EditAmbassador;
use App\Filament\Resources\Ambassadors\Pages\ListAmbassadors;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Ambassador;
use BackedEnum;
use Filament\Actions\EditAction;
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
use Illuminate\Support\Str;
use UnitEnum;

class AmbassadorResource extends Resource
{
    protected static ?string $model = Ambassador::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Đại sứ & hoa hồng';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'đại sứ';
    protected static ?string $pluralModelLabel = 'Đại sứ';
    protected static ?string $recordTitleAttribute = 'name';

    public const TYPES = ['guide' => 'HDV, tài xế, lữ hành', 'guide_regular' => 'Đối tác đưa khách đều', 'staff' => 'Nhân viên nội bộ', 'koc' => 'KOC', 'agent' => 'Đại lý'];
    public const PREFIX = ['guide' => 'HDV', 'guide_regular' => 'LH', 'staff' => 'NV', 'koc' => 'KOC', 'agent' => 'DL'];

    public static function makeCode(string $name, string $type): string
    {
        $last = strtoupper(Str::ascii(Str::afterLast(trim($name), ' ')));
        $base = (self::PREFIX[$type] ?? 'DS') . substr(preg_replace('/[^A-Z0-9]/', '', $last) ?: 'X', 0, 6);
        $i = 1; do { $code = $base . str_pad((string) $i++, 2, '0', STR_PAD_LEFT); } while (Ambassador::where('ref_code', $code)->exists());
        return $code;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
                TextInput::make('phone')->label('Số điện thoại')->required()->regex('/^0\d{9}$/')->unique(ignoreRecord: true)
                    ->validationMessages(['regex' => 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.']),
                Select::make('type')->label('Loại')->options(self::TYPES)->required(),
                TextInput::make('ref_code')->label('Mã giới thiệu')->maxLength(32)->unique(ignoreRecord: true)->regex('/^[A-Z0-9_-]{2,32}$/')
                    ->helperText('Để trống, hệ thống tự tạo theo loại và tên. Chỉ dùng chữ in hoa và số.')
                    ->dehydrateStateUsing(fn ($state) => $state ? strtoupper($state) : null),
                Select::make('parent_id')->label('Người giới thiệu (đầu mối)')->relationship('parent', 'name')->searchable()->preload(),
                Select::make('status')->label('Trạng thái')->options(['active' => 'Đang hoạt động', 'paused' => 'Tạm dừng'])->default('active')->required(),
                Textarea::make('bank_info')->label('Thông tin nhận tiền (được mã hóa)')->rows(2)->columnSpanFull()->placeholder('Ngân hàng, số tài khoản, tên chủ tài khoản'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Đại sứ')->searchable()->weight('bold')->description(fn ($record) => $record->phone),
                TextColumn::make('type')->label('Loại')->badge()->formatStateUsing(fn ($state) => self::TYPES[$state] ?? $state),
                TextColumn::make('ref_code')->label('Mã')->searchable()->copyable()->copyMessage('Đã chép mã'),
                TextColumn::make('link')->label('Link giới thiệu')->getStateUsing(fn ($record) => url('/?ref=' . $record->ref_code))->copyable()->copyMessage('Đã chép link')->limit(40),
                TextColumn::make('orders_count')->label('Đơn')->counts('orders')->alignCenter(),
                TextColumn::make('commission_total')->label('Hoa hồng')->alignEnd()
                    ->getStateUsing(fn ($record) => OrderResource::vnd($record->commissions()->whereIn('status', ['pending', 'approved', 'paid'])->sum('amount'))),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => $state === 'active' ? 'Hoạt động' : 'Tạm dừng')->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('type')->label('Loại')->options(self::TYPES)])
            ->recordActions([EditAction::make()->label('Sửa')])
            ->emptyStateHeading('Chưa có đại sứ')->emptyStateDescription('Bấm "Tạo đại sứ" để tạo mã giới thiệu đầu tiên.');
    }

    public static function getPages(): array
    {
        return ['index' => ListAmbassadors::route('/'), 'create' => CreateAmbassador::route('/create'), 'edit' => EditAmbassador::route('/{record}/edit')];
    }
}
