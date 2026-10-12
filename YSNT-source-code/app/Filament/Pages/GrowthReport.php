<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\Growth\ConnectionsCumulative;
use App\Filament\Widgets\Growth\CustomersMonthly;
use App\Filament\Widgets\Growth\GrowthKpis;
use App\Filament\Widgets\Growth\RevenueCumulative;
use App\Filament\Widgets\Growth\RevenueMonthly;
use App\Models\AppSetting;
use App\Support\Growth;
use App\Support\Targets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** "Tăng trưởng từ 0": every number starts at 0 on the start date and is recomputed from the one database */
class GrowthReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;
    protected static string|UnitEnum|null $navigationGroup = 'Báo cáo';
    protected static ?int $navigationSort = -10;
    protected static ?string $navigationLabel = 'Tăng trưởng từ 0';
    protected static ?string $title = 'Tăng trưởng từ 0';
    protected static ?string $slug = 'tang-truong';
    protected string $view = 'filament.pages.growth';

    public static function canAccess(): bool { return in_array(auth()->user()?->role, ['admin', 'accountant'], true); }


    protected function getHeaderWidgets(): array { return [GrowthKpis::class, RevenueCumulative::class, RevenueMonthly::class, CustomersMonthly::class, ConnectionsCumulative::class]; }
    public function getHeaderWidgetsColumns(): int|array { return ['default' => 1, 'lg' => 2]; }

    /** one row per month: the table view of every chart (also the CSV) */
    public static function rows(): array
    {
        $rev = Growth::revenue(); $ord = Growth::orders(); $buy = Growth::newBuyers(); $conn = Growth::connections(); $conv = Growth::conversations();
        $ai = Growth::aiHandled(); $tour = Growth::tourVisits(); $pax = Growth::tourPax(); $staff = Growth::staffSales(); $tgt = Growth::targets();
        $cum = Growth::cumulative(array_map('array_sum', array_map(null, ...array_values($rev))));
        $kb = Growth::kbQuestions(); $cv = Growth::applications();
        $out = [];
        foreach (Growth::months() as $i => $m) {
            $out[] = ['Tháng' => Growth::label($m)] + collect(Targets::GROUPS)->mapWithKeys(fn ($l, $g) => ["Doanh thu $l" => $rev[$g][$i]])->all()
                + ['Doanh thu lũy kế' => $cum[$i], 'Chỉ tiêu tháng' => $tgt[$i], 'Đơn' => $ord[$i], 'Khách mới' => $buy[$i]]
                + collect(Growth::PROVIDERS)->mapWithKeys(fn ($l, $p) => ["Kết nối $l" => $conn[$p][$i]])->all()
                + ['Hội thoại' => $conv[$i], 'AI tự xử lý' => $ai[$i], 'Xe tour' => $tour[$i], 'Khách xe tour' => $pax[$i], 'Doanh số mã NV' => $staff[$i], 'Câu hỏi AI nội bộ' => $kb[$i], 'Hồ sơ ứng tuyển' => $cv[$i]];
        }
        return $out;
    }

    public function getSubheading(): ?string
    {
        return 'Mọi con số bắt đầu từ 0 vào ngày ' . Growth::start()->format('d/m/Y') . ' và chỉ tính những gì hệ thống ghi nhận từ ngày đó: đơn mọi kênh, khách, kết nối Zalo, Facebook, TikTok, hội thoại, xe tour, nhân sự. Không nhập tay, không lấy số cũ. Đơn hủy, đơn trả không tính.';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('start')->label('Đổi mốc 0')->icon(Heroicon::OutlinedCalendar)->visible(fn () => auth()->user()?->role === 'admin')
            ->fillForm(fn () => ['day' => Growth::start()->toDateString()])
            ->schema([\Filament\Forms\Components\DatePicker::make('day')->label('Bắt đầu tính từ')->required()->maxDate(now())->native(false)->displayFormat('d/m/Y')])
            ->modalDescription('Thường đặt đúng ngày hệ thống bắt đầu chạy thật. Dữ liệu trước ngày này không tính.')
            ->action(function (array $data) { AppSetting::put('growth_start', \Illuminate\Support\Carbon::parse($data['day'])->toDateString()); Notification::make()->title('Đã đổi mốc 0, số liệu tính lại')->success()->send(); $this->redirect(static::getUrl()); }),
            Action::make('csv')->label('Xuất CSV')->icon(Heroicon::OutlinedArrowDownTray)->action(function () {
            $rows = self::rows();
            return response()->streamDownload(function () use ($rows) {
                $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF");
                if ($rows) fputcsv($f, array_keys($rows[0]));
                foreach ($rows as $r) fputcsv($f, $r);
                fclose($f);
            }, 'tang-truong-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        })];
    }
}
