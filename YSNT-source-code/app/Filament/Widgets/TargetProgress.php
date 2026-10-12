<?php
namespace App\Filament\Widgets;

use App\Support\Targets;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TargetProgress extends StatsOverviewWidget
{
    protected static ?int $sort = -1;
    protected ?string $heading = 'Mục tiêu 60 tỷ';

    protected function getStats(): array
    {
        $from = now()->startOfMonth();
        $has = DB::table('sales_targets')->where('period', $from->toDateString())->exists();
        if (! $has) {
            $first = DB::table('sales_targets')->min('period');
            if (! $first) return [Stat::make('Chưa có chỉ tiêu', '—')];
            $from = Carbon::parse($first)->startOfMonth();
            $this->heading = 'Mục tiêu 60 tỷ · chỉ tiêu bắt đầu tháng ' . $from->format('m/Y');
        } else {
            $this->heading = 'Mục tiêu 60 tỷ · tháng ' . $from->format('m/Y');
        }
        $to = $from->copy()->addMonth();
        $act = Targets::actual($from, $to); $tgt = Targets::target($from, $to); $str = Targets::stretch($from, $to);
        $yFrom = $from->copy()->startOfYear(); $yTo = $yFrom->copy()->addYear();
        $yAct = array_sum(Targets::actual($yFrom, $yTo)); $yTgt = array_sum(Targets::target($yFrom, $yTo)); $yStr = array_sum(Targets::stretch($yFrom, $yTo));
        $more = fn (int $s, int $t) => $s > $t ? ' · phấn đấu ' . Targets::ty($s) : '';
        $this->heading = str_replace('Mục tiêu 60 tỷ', 'Mục tiêu ' . $yFrom->year . ': ' . Targets::ty($yTgt) . ($yStr > $yTgt ? ', phấn đấu ' . Targets::ty($yStr) : ''), (string) $this->heading);
        $pct = fn ($a, $t) => $t ? round($a / $t * 100) . '%' : '—';
        $color = fn ($a, $t) => ! $t ? 'gray' : ($a >= $t ? 'success' : ($a >= $t * 0.7 ? 'warning' : 'danger'));
        $stats = [Stat::make('Cả tháng', $pct(array_sum($act), array_sum($tgt)))->description(Targets::ty(array_sum($act)) . ' / ' . Targets::ty(array_sum($tgt)) . $more(array_sum($str), array_sum($tgt)))->color($color(array_sum($act), array_sum($tgt)))];
        foreach (Targets::GROUPS as $g => $label) {
            $stats[] = Stat::make($label, $pct($act[$g], $tgt[$g]))->description(Targets::ty($act[$g]) . ' / ' . Targets::ty($tgt[$g]) . $more($str[$g], $tgt[$g]))->color($color($act[$g], $tgt[$g]));
        }
        $stats[] = Stat::make('Cả năm ' . $yFrom->year, $pct($yAct, $yTgt))->description(Targets::ty($yAct) . ' / ' . Targets::ty($yTgt) . $more($yStr, $yTgt));
        return $stats;
    }
}
