<?php
namespace App\Filament\Pages;

use App\Support\Roadmap;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RoadmapPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;
    protected static string|UnitEnum|null $navigationGroup = 'Báo cáo';
    protected static ?int $navigationSort = -5;
    protected static ?string $navigationLabel = 'Lộ trình triển khai';
    protected static ?string $title = 'Lộ trình triển khai theo kế hoạch';
    protected static ?string $slug = 'lo-trinh';
    protected string $view = 'filament.pages.roadmap';
    public array $st = [];
    public array $note = [];

    public static function canAccess(): bool { return in_array(auth()->user()?->role, ['admin', 'accountant'], true); }
    public function getSubheading(): ?string { return 'Các hạng mục kế hoạch 60 tỷ cam kết xây (mục 10 đến 13), quý dự kiến và tình trạng. Quá quý mà chưa chạy thật thì báo trễ.'; }

    public function mount(): void
    {
        foreach (Roadmap::all() as $i) { $this->st[$i['code']] = $i['status']; $this->note[$i['code']] = $i['note']; }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        Roadmap::save($this->st, $this->note);
        Notification::make()->title('Đã lưu lộ trình')->success()->send();
    }
}
