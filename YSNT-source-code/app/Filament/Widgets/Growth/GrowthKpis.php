<?php
namespace App\Filament\Widgets\Growth;

use App\Support\Growth;
use App\Support\Targets;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** headline tiles: total since the start date (from 0), month-over-month change, cumulative sparkline */
class GrowthKpis extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = null;
    public static function isDiscovered(): bool { return false; }

    protected function getColumns(): int|array { return ['default' => 1, 'md' => 2, 'xl' => 4]; }

    public static function tiles(): array
    {
        $n = fn ($v) => number_format((int) $v, 0, ',', '.');
        $rev = array_map('array_sum', array_map(null, ...array_values(Growth::revenue())));
        $ord = Growth::orders(); $buy = Growth::newBuyers(); $conn = array_map('array_sum', array_map(null, ...array_values(Growth::connections())));
        $conv = Growth::conversations(); $ai = Growth::aiHandled(); $tour = Growth::tourVisits(); $staff = Growth::staffSales();
        $rep = Growth::repeat(); $kept = Growth::contactKept();
        $pct = fn ($a, $b) => $b ? round($a / $b * 100) . '%' : '0%';
        $tiles = [
            ['Doanh thu', Targets::ty(array_sum($rev)), Growth::delta($rev), Growth::cumulative($rev)],
            ['Đơn hàng', $n(array_sum($ord)), Growth::delta($ord), Growth::cumulative($ord)],
            ['Khách đã mua', $n(array_sum($buy)), Growth::delta($buy), Growth::cumulative($buy)],
            ['Khách mua lại', $pct($rep['repeat'], $rep['buyers']), $n($rep['repeat']) . ' / ' . $n($rep['buyers']) . ' khách · mục tiêu 20%', null],
            ['Lưu liên hệ khách tại chỗ', $pct($kept['kept'], $kept['orders']), $n($kept['kept']) . ' / ' . $n($kept['orders']) . ' đơn showroom · mục tiêu 60%', null],
            ['Kết nối khách (Zalo, FB, IG, TikTok, web)', $n(array_sum($conn)), Growth::delta($conn), Growth::cumulative($conn)],
            ['Hội thoại tư vấn', $n(array_sum($conv)), 'AI tự xử lý ' . $pct(array_sum($ai), array_sum($conv)), Growth::cumulative($conv)],
            ['Doanh số qua mã nhân viên', Targets::ty(array_sum($staff)), Growth::delta($staff), Growth::cumulative($staff)],
            ['Lượt xe tour ghé', $n(array_sum($tour)), Growth::delta($tour), Growth::cumulative($tour)],
        ];
        // internal side: staff actually using the Workplace, asking the AI, recruitment
        $demo = \App\Hr\Hr::demoIds();
        $wp = Growth::optional('employees', 'last_login_at', fn ($q) => $q->whereNotIn('id', $demo)); $kb = Growth::kbQuestions();
        $cv = Growth::applications(); $hired = Growth::optional('job_applications', 'updated_at', fn ($q) => $q->where('status', 'hired')->whereNull('deleted_at'));
        $tiles[] = ['Nhân viên đã vào Workplace', $n(array_sum($wp)), 'Tính theo lần đăng nhập gần nhất', null];
        $tiles[] = ['Câu hỏi nội bộ AI giải đáp', $n(array_sum($kb)), Growth::delta($kb), Growth::cumulative($kb)];
        $tiles[] = ['Hồ sơ ứng tuyển', $n(array_sum($cv)), 'Đã nhận việc: ' . $n(array_sum($hired)), Growth::cumulative($cv)];
        return $tiles;
    }

    protected function getStats(): array
    {
        return array_map(fn ($t) => tap(Stat::make($t[0], $t[1])->description($t[2]), fn ($s) => $t[3] && count($t[3]) > 1 ? $s->chart($t[3])->chartColor('primary') : null), self::tiles());
    }
}
