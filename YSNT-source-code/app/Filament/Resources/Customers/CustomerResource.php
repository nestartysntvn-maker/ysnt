<?php
namespace App\Filament\Resources\Customers;

use App\Customers\CustomerImport;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ImportCustomers;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Customer;
use App\Models\CustomerImportBatch;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string|UnitEnum|null $navigationGroup = 'Bán hàng';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'khách hàng';
    protected static ?string $pluralModelLabel = 'Khách hàng';
    protected static ?string $recordTitleAttribute = 'name';

    /** adding customers by hand: Quản trị and Bán hàng, same as importing a list */
    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasRole('sales');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
                TextInput::make('phone')->label('Số điện thoại')->required()->maxLength(40)
                    ->helperText('Gõ kiểu nào cũng được: 0905 123 456, +84 905 123 456, 0168…')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $p = CustomerImport::phone($state);
                        if ($p['err'] === null && $p['v'] !== $state) $set('phone', $p['v']);
                    })
                    ->dehydrateStateUsing(fn (?string $state) => ($p = CustomerImport::phone($state))['err'] === null ? $p['v'] : $state)
                    ->rules([fn (?Customer $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                        if ($record && $value === $record->phone) return; // number not changed
                        $p = CustomerImport::phone($value);
                        if ($p['err'] !== null) { $fail($p['err'] . '.'); return; }
                        $c = Customer::where('phone', $p['v'])->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->first();
                        if ($c) $fail('Số này đã có trong danh sách: ' . $c->name . '.');
                    }]),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                DatePicker::make('birthday')->label('Ngày sinh')->displayFormat('d/m/Y')->native(false)->maxDate(now()),
                Select::make('customer_group')->label('Nhóm khách')->options(Customer::GROUPS)->placeholder('Chưa phân nhóm')->default('le'),
                Select::make('source')->label('Nguồn')->options(Customer::SOURCES)->placeholder('Tự tạo khi đặt đơn')->default('showroom'),
                TextInput::make('first_ref_code')->label('Mã giới thiệu lần đầu')->disabled()->hiddenOn('create'),
                Placeholder::make('batch_v')->label('Nhập từ')->hiddenOn('create')
                    ->content(fn ($record) => $record?->import_batch_id ? 'Lô ' . $record->import_batch_id . ' · ' . ($record->importBatch?->file_name ?? '') : 'Tự tạo khi đặt đơn hoặc thêm tay'),
                Textarea::make('address')->label('Địa chỉ')->rows(2)->columnSpanFull(),
                Textarea::make('note')->label('Ghi chú')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Lịch sử mua')->columnSpanFull()->hiddenOn('create')->schema([
                Placeholder::make('orders_v')->hiddenLabel()->content(fn ($record) => new HtmlString($record ? ($record->orders()->latest()->get()
                    ->map(fn ($o) => '<a style="text-decoration:underline" href="' . OrderResource::getUrl('edit', ['record' => $o]) . '">' . e($o->code) . '</a> · ' . $o->created_at->format('d/m/Y') . ' · ' . OrderResource::vnd($o->total) . ' · ' . e(OrderResource::STATUS[$o->status] ?? $o->status))
                    ->implode('<br>') ?: 'Chưa có đơn.') : '')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Khách')->searchable()->weight('bold')->description(fn ($record) => $record->phone),
                TextColumn::make('phone')->label('SĐT')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer_group')->label('Nhóm')->badge()->formatStateUsing(fn ($state) => Customer::GROUPS[$state] ?? $state)->placeholder('—')->toggleable(),
                TextColumn::make('source')->label('Nguồn')->formatStateUsing(fn ($state) => Customer::SOURCES[$state] ?? $state)->placeholder('—')->toggleable(),
                TextColumn::make('orders_count')->label('Số đơn')->counts('orders')->alignCenter()->sortable(),
                TextColumn::make('orders_sum_total')->label('Tổng chi tiêu')->sum('orders', 'total')->formatStateUsing(fn ($state) => OrderResource::vnd($state))->alignEnd()->sortable(),
                TextColumn::make('first_ref_code')->label('Mã giới thiệu lần đầu')->placeholder('—')->toggleable(),
                TextColumn::make('orders_min_created_at')->label('Mua lần đầu')->min('orders', 'created_at')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Ngày thêm')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_group')->label('Nhóm khách')->options(Customer::GROUPS),
                SelectFilter::make('source')->label('Nguồn')->options(Customer::SOURCES),
                SelectFilter::make('import_batch_id')->label('Lô nhập')->options(fn () => CustomerImportBatch::latest('id')->limit(50)->get()
                    ->mapWithKeys(fn ($b) => [$b->id => 'Lô ' . $b->id . ' · ' . $b->file_name . ($b->undone_at ? ' (đã hoàn tác)' : '')])->all()),
            ])
            ->recordActions([EditAction::make()->label('Xem')])
            ->emptyStateHeading('Chưa có khách hàng')
            ->emptyStateDescription('Khách được tạo tự động khi đặt đơn đầu tiên. Có thể thêm từng khách, hoặc nhập danh sách khách cũ từ file Excel.')
            ->emptyStateActions([static::createButton(), static::importAction()->color('gray')]);
    }

    /** "Thêm khách" button -> create page */
    public static function createButton(): Action
    {
        return Action::make('add')->label('Thêm khách')->icon(Heroicon::OutlinedPlus)
            ->url(fn () => static::getUrl('create'))->visible(fn () => static::canCreate());
    }

    /** "Nhập danh sách" button: Quản trị and Bán hàng */
    public static function importAction(): Action
    {
        return Action::make('import')->label('Nhập danh sách')->icon(Heroicon::OutlinedArrowUpTray)
            ->url(fn () => static::getUrl('import'))->visible(fn () => ImportCustomers::canAccess());
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomers::route('/'), 'create' => CreateCustomer::route('/create'), 'import' => ImportCustomers::route('/nhap'), 'edit' => EditCustomer::route('/{record}/edit')];
    }
}
