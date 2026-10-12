<?php
namespace App\Filament\Resources\Conversations;

use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Models\Conversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;
    protected static string|UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'hội thoại';
    protected static ?string $pluralModelLabel = 'Hộp thư chung';
    protected static ?string $navigationLabel = 'Hộp thư chung';

    public static function canViewAny(): bool { return in_array(auth()->user()?->role, ['admin', 'sales'], true); }
    public static function canCreate(): bool { return false; }
    public static function form(Schema $schema): Schema { return $schema; }

    public static function getNavigationBadge(): ?string
    {
        $n = Conversation::where('status', 'open')->where(fn ($q) => $q->where('needs_human', true)->orWhere('unread', '>', 0))->count();
        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['customer', 'assignee'])->orderByRaw("status = 'open' desc")->orderByDesc('needs_human')->orderByDesc('last_message_at'))
            ->poll('20s')
            ->columns([
                TextColumn::make('channel')->label('Kênh')->badge()->formatStateUsing(fn ($state) => Conversation::CHANNELS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'zalo' => 'info', 'facebook', 'instagram' => 'primary', 'tiktok' => 'gray', default => 'success' }),
                TextColumn::make('name')->label('Khách')->weight('bold')->description(fn ($record) => $record->customer?->phone ? $record->customer->name . ' · ' . $record->customer->phone : 'Chưa gắn khách hàng')->searchable(),
                TextColumn::make('last_preview')->label('Tin gần nhất')->limit(70)->wrap(),
                TextColumn::make('tag')->label('Nhóm')->badge()->formatStateUsing(fn ($state) => Conversation::TAGS[$state] ?? $state)->placeholder('—'),
                IconColumn::make('needs_human')->label('Cần người')->boolean()->trueColor('danger')->falseIcon(''),
                TextColumn::make('unread')->label('Chưa đọc')->badge()->color('danger')->formatStateUsing(fn ($state) => $state ?: '')->alignCenter(),
                TextColumn::make('last_message_at')->label('Lúc')->since()->sortable(),
                TextColumn::make('assignee.name')->label('Phụ trách')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('channel')->label('Kênh')->options(Conversation::CHANNELS),
                SelectFilter::make('status')->label('Trạng thái')->options(Conversation::STATUS)->default('open'),
                SelectFilter::make('tag')->label('Nhóm')->options(Conversation::TAGS),
                TernaryFilter::make('needs_human')->label('Cần người trả lời'),
            ])
            ->recordUrl(fn ($record) => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Chưa có tin nhắn')->emptyStateDescription('Tin từ chat web, Zalo OA, Messenger, Instagram, TikTok Shop sẽ về đây khi các kênh được kết nối.');
    }

    public static function getPages(): array { return ['index' => ListConversations::route('/'), 'view' => ViewConversation::route('/{record}')]; }
}
