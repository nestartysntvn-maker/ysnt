<?php
namespace App\Support;

use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\Order;
use Illuminate\Support\Carbon;

/** The year's sales goal (2027: 60 tỷ in sales_targets) as everyone on the Workplace sees it. */
class YearGoal
{
    public static function year(): int { return (int) (AppSetting::get('goal_year') ?: 2027); }

    public static function data(?Employee $me = null): ?array
    {
        $y = self::year();
        $from = Carbon::create($y, 1, 1, 0, 0, 0, 'Asia/Ho_Chi_Minh'); $to = $from->copy()->addYear();
        $target = Targets::target($from, $to); $stretch = Targets::stretch($from, $to);
        if (! array_sum($target)) return null;
        $actual = Targets::actual($from->copy()->tz('UTC'), $to->copy()->tz('UTC'));
        $now = now('Asia/Ho_Chi_Minh'); $started = $now->gte($from);
        $mFrom = ($started && $now->lt($to) ? $now->copy()->startOfMonth() : $from->copy());
        $mTarget = array_sum(Targets::target($mFrom, $mFrom->copy()->addMonth())); $mStretch = array_sum(Targets::stretch($mFrom, $mFrom->copy()->addMonth()));
        $mActual = array_sum(Targets::actual($mFrom->copy()->tz('UTC'), $mFrom->copy()->addMonth()->tz('UTC')));
        $quarters = [];
        foreach ([1, 2, 3, 4] as $q) { $qf = $from->copy()->addMonths(($q - 1) * 3); $quarters[$q] = array_sum(Targets::target($qf, $qf->copy()->addMonths(3))); }
        $mine = null;
        if ($me?->ambassador_id) $mine = (int) Order::where('ambassador_id', $me->ambassador_id)->whereNotIn('status', ['cancelled', 'returned'])
            ->where('created_at', '>=', $from->copy()->tz('UTC'))->where('created_at', '<', $to->copy()->tz('UTC'))->sum('total');
        return ['year' => $y, 'target' => array_sum($target), 'stretch' => array_sum($stretch), 'actual' => array_sum($actual),
            'groups' => collect(Targets::GROUPS)->map(fn ($l, $g) => ['label' => $l, 't' => $target[$g], 's' => max($stretch[$g], $target[$g]), 'a' => $actual[$g]])->all(),
            'started' => $started, 'daysToStart' => $started ? 0 : (int) ceil($now->diffInDays($from)), 'month' => ['label' => 'Tháng ' . $mFrom->format('n/Y'), 't' => $mTarget, 's' => $mStretch, 'a' => $mActual],
            'quarters' => $quarters, 'mine' => $mine, 'elapsedPct' => $started ? min(100, round($from->diffInDays($now) / $from->diffInDays($to) * 100)) : 0];
    }
}
