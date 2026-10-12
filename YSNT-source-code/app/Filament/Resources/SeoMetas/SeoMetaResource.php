<?php
namespace App\Filament\Resources\SeoMetas;

use App\Ai\Assist;
use App\Filament\Resources\SeoMetas\Pages\EditSeoMeta;
use App\Filament\Resources\SeoMetas\Pages\ListSeoMetas;
use App\Models\SeoMeta;
use App\Support\Seo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SeoMetaResource extends Resource
{
    protected static ?string $model = SeoMeta::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;
    protected static string|UnitEnum|null $navigationGroup = 'SEO';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'SEO trang';
    protected static ?string $pluralModelLabel = 'SEO từng trang';
    protected static ?string $navigationLabel = 'SEO từng trang';

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'content'], true); }
    public static function canCreate(): bool { return false; }

    private static function info(SeoMeta $m): array { return Seo::info($m->page_type, $m->page_key) ?? ['name' => '(trang đã xóa)', 'url' => '', 'title' => '', 'desc' => '']; }

    /** run the AI on one row; returns null when fine, otherwise the reason */
    public static function aiFill(SeoMeta $m): ?string
    {
        $r = Assist::seo($m->page_type, $m->page_key);
        if ($r['status'] !== 'ok') return $r['error'];
        $m->update(['meta_title' => $r['data']['title'], 'meta_description' => $r['data']['description'], 'focus_keyword' => $m->focus_keyword ?: $r['data']['keyword'], 'updated_by' => auth()->id()]);
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        $count = fn (int $max) => fn ($state) => mb_strlen((string) $state) . '/' . $max . ' ký tự' . (mb_strlen((string) $state) > $max ? ' · hơi dài, Google có thể cắt bớt' : '');
        return $schema->components([
            Section::make('Trang')->columnSpanFull()->schema([
                Placeholder::make('page')->hiddenLabel()->content(fn ($record) => $record ? new HtmlString('<b>' . e(self::info($record)['name']) . '</b> · <a href="' . e(self::info($record)['url']) . '" target="_blank" style="text-decoration:underline">' . e(self::info($record)['url']) . '</a>'
                    . '<br><span style="opacity:.7">Mặc định: ' . e(self::info($record)['title']) . '</span>') : ''),
            ]),
            Section::make('Hiển thị trên Google')->columns(2)->columnSpanFull()->schema([
                TextInput::make('meta_title')->label('Tiêu đề')->maxLength(70)->live(debounce: 400)->helperText($count(Seo::TITLE_MAX))->columnSpanFull()
                    ->placeholder(fn ($record) => $record ? self::info($record)['title'] : null),
                Textarea::make('meta_description')->label('Mô tả')->maxLength(320)->rows(3)->live(debounce: 400)->helperText($count(Seo::DESC_MAX))->columnSpanFull(),
                TextInput::make('focus_keyword')->label('Từ khóa chính')->maxLength(100),
                TextInput::make('og_image')->label('Ảnh khi chia sẻ (link)')->url()->maxLength(500),
                Toggle::make('noindex')->label('Ẩn trang này khỏi Google')->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $len = fn (int $max) => fn ($state) => $state ? mb_strlen($state) . '/' . $max : 'chưa có';
        return $table->defaultSort('page_type')
            ->columns([
                TextColumn::make('page_type')->label('Loại')->badge()->formatStateUsing(fn ($state) => SeoMeta::TYPES[$state] ?? $state),
                TextColumn::make('page_key')->label('Trang')->formatStateUsing(fn ($record) => self::info($record)['name'])->description(fn ($record) => self::info($record)['url'])->wrap(),
                TextColumn::make('meta_title')->label('Tiêu đề')->limit(60)->placeholder('Dùng mặc định')->description(fn ($record) => $len(Seo::TITLE_MAX)($record->meta_title)),
                TextColumn::make('meta_description')->label('Mô tả')->limit(80)->wrap()->placeholder('Chưa có')->description(fn ($record) => $len(Seo::DESC_MAX)($record->meta_description)),
                TextColumn::make('focus_keyword')->label('Từ khóa')->placeholder('—'),
                IconColumn::make('noindex')->label('Ẩn')->boolean()->trueColor('warning')->falseIcon(''),
            ])
            ->filters([
                SelectFilter::make('page_type')->label('Loại')->options(SeoMeta::TYPES),
                Filter::make('missing')->label('Thiếu tiêu đề hoặc mô tả')->query(fn ($query) => $query->where(fn ($w) => $w->whereNull('meta_title')->orWhereNull('meta_description'))),
            ])
            ->headerActions([
                Action::make('sync')->label('Thêm dòng cho trang chưa có')->icon(Heroicon::OutlinedPlus)
                    ->action(fn () => Notification::make()->title('Đã thêm ' . Seo::sync() . ' trang')->success()->send()),
            ])
            ->recordActions([
                Action::make('ai')->label('AI viết')->icon(Heroicon::OutlinedSparkles)->requiresConfirmation()->modalDescription('AI viết tiêu đề và mô tả mới cho trang này, ghi đè nội dung đang có.')
                    ->action(function (SeoMeta $record) { $e = self::aiFill($record); $e ? Notification::make()->title('AI chưa viết được')->body($e)->warning()->send() : Notification::make()->title('Đã viết xong, xem lại rồi sửa nếu cần')->success()->send(); }),
                EditAction::make()->label('Sửa'),
            ])
            ->toolbarActions([
                BulkAction::make('aiMany')->label('AI viết cho các dòng đã chọn')->icon(Heroicon::OutlinedSparkles)->requiresConfirmation()
                    ->modalDescription('Tối đa 20 dòng mỗi lần. Ghi đè tiêu đề, mô tả đang có.')
                    ->action(function (Collection $records) {
                        $ok = 0; $err = null;
                        foreach ($records->take(20) as $r) { $e = self::aiFill($r); if ($e) { $err = $e; break; } $ok++; }
                        Notification::make()->title("AI đã viết $ok trang")->body($err)->{$err ? 'warning' : 'success'}()->send();
                    }),
            ])
            ->emptyStateHeading('Chưa có dòng SEO nào')->emptyStateDescription('Bấm "Thêm dòng cho trang chưa có" để tạo sẵn cho mọi sản phẩm, danh mục, trang chính.');
    }

    public static function getPages(): array { return ['index' => ListSeoMetas::route('/'), 'edit' => EditSeoMeta::route('/{record}/edit')]; }
}
