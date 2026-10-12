<?php
namespace App\Filament\Resources\KbDocs;

use App\Filament\Resources\KbDocs\Pages\CreateKbDoc;
use App\Filament\Resources\KbDocs\Pages\EditKbDoc;
use App\Filament\Resources\KbDocs\Pages\ListKbDocs;
use App\Hr\Hr;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KbDoc;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class KbDocResource extends Resource
{
    protected static ?string $model = KbDoc::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;
    protected static string|UnitEnum|null $navigationGroup = 'Nhân sự';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'tài liệu';
    protected static ?string $pluralModelLabel = 'Quy trình, tài liệu';
    protected static ?string $navigationLabel = 'Quy trình, tài liệu';

    public static function canViewAny(): bool { return Hr::can('kb'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('title')->label('Tên quy trình, tài liệu')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('category')->label('Nhóm')->maxLength(64)->datalist(fn () => KbDoc::whereNotNull('category')->distinct()->pluck('category')->all())->placeholder('Bán hàng, Kho, Nhân sự…'),
                Select::make('department_id')->label('Dành cho')->options(fn () => Department::where('is_active', true)->pluck('name', 'id'))->placeholder('Toàn công ty'),
                Select::make('owner_employee_id')->label('Người phụ trách')->searchable()->options(fn () => Employee::where('status', 'active')->orderBy('full_name')->pluck('full_name', 'id'))
                    ->helperText('AI chỉ người này khi tài liệu chưa có câu trả lời.'),
                Toggle::make('is_published')->label('Hiện cho nhân viên')->default(true)->inline(false),
                Textarea::make('body')->label('Nội dung')->rows(16)->columnSpanFull()->helperText('Viết từng bước, mỗi bước một dòng. Có tệp đính kèm thì phần này có thể để trống.'),
                FileUpload::make('file_path')->label('Tệp đính kèm (PDF, Word, TXT; tối đa 20MB)')->disk('local')->directory('kb')->storeFileNamesIn('file_name')->maxSize(20480)->columnSpanFull()
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/markdown'])
                    ->helperText('Hệ thống tự lấy chữ trong tệp để tìm kiếm và để AI đọc.'),
                Placeholder::make('extracted')->label('Chữ đọc được từ tệp')->columnSpanFull()->visible(fn (?KbDoc $record) => (bool) $record?->file_path)
                    ->content(fn (?KbDoc $record) => $record?->file_text ? number_format(mb_strlen($record->file_text)) . ' ký tự. Mở đầu: ' . mb_strimwidth(preg_replace('/\s+/', ' ', $record->file_text), 0, 300, '…') : 'Không đọc được chữ (tệp ảnh scan?). Nên gõ nội dung vào ô Nội dung.'),
                TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('category')->modifyQueryUsing(fn ($query) => $query->with(['department', 'owner']))->columns([
            TextColumn::make('title')->label('Tài liệu')->weight('bold')->searchable()->description(fn ($record) => $record->category),
            TextColumn::make('department.name')->label('Dành cho')->placeholder('Toàn công ty'),
            TextColumn::make('owner.full_name')->label('Phụ trách')->placeholder('—'),
            TextColumn::make('file_name')->label('Tệp')->placeholder('—')->limit(30),
            TextColumn::make('size')->label('Độ dài')->state(fn ($record) => number_format(mb_strlen($record->text())) . ' ký tự'),
            IconColumn::make('is_published')->label('Hiện')->boolean(),
            TextColumn::make('updated_at')->label('Cập nhật')->since()->sortable(),
        ])->filters([SelectFilter::make('department_id')->label('Dành cho')->relationship('department', 'name')])
            ->recordActions([EditAction::make()->label('Sửa')])
            ->emptyStateHeading('Chưa có quy trình nào')->emptyStateDescription('Thêm quy trình để nhân viên tra cứu và hỏi AI trên Workplace.');
    }

    public static function getPages(): array { return ['index' => ListKbDocs::route('/'), 'create' => CreateKbDoc::route('/create'), 'edit' => EditKbDoc::route('/{record}/edit')]; }
}
