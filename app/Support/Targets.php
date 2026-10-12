<?php
namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Quy tắc xếp đơn vào 3 nhóm chỉ tiêu (anh đã duyệt):
 *  1) có mã nhân viên nội bộ -> b2b_noi_bo; 2) đại lý, quà DN -> b2b_noi_bo; 3) showroom -> tai_cho; 4) còn lại -> online. */
class Targets
{
    public const GROUPS = ['tai_cho' => 'Tại chỗ', 'online' => 'Online', 'b2b_noi_bo' => 'B2B và nội bộ'];

    public static function groupSql(): string
    {
        return "CASE WHEN a.type = 'staff' THEN 'b2b_noi_bo' WHEN o.channel IN ('agent','corporate') THEN 'b2b_noi_bo' WHEN o.channel = 'showroom' THEN 'tai_cho' ELSE 'online' END";
    }

    /** @return array<string,int> actual revenue per group between [from, to) */
    public static function actual(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('orders as o')->leftJoin('ambassadors as a', 'a.id', '=', 'o.ambassador_id')
            ->whereNotIn('o.status', ['cancelled', 'returned'])->where('o.created_at', '>=', $from)->where('o.created_at', '<', $to)
            ->selectRaw(self::groupSql() . ' as g, SUM(o.total) as s')->groupBy('g')->pluck('s', 'g');
        return collect(self::GROUPS)->map(fn ($l, $g) => (int) ($rows[$g] ?? 0))->all();
    }

    /** @return array<string,int> targets per group for months in [from, to) */
    public static function target(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('sales_targets')->where('period', '>=', $from->toDateString())->where('period', '<', $to->toDateString())
            ->selectRaw('channel_group, SUM(target_amount) s')->groupBy('channel_group')->pluck('s', 'channel_group');
        return collect(self::GROUPS)->map(fn ($l, $g) => (int) ($rows[$g] ?? 0))->all();
    }

    /** @return array<string,int> stretch goal per group (falls back to the target where no stretch is set) */
    public static function stretch(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('sales_targets')->where('period', '>=', $from->toDateString())->where('period', '<', $to->toDateString())
            ->selectRaw('channel_group, SUM(COALESCE(stretch_amount, target_amount)) s')->groupBy('channel_group')->pluck('s', 'channel_group');
        return collect(self::GROUPS)->map(fn ($l, $g) => (int) ($rows[$g] ?? 0))->all();
    }

    public static function ty(int $v): string
    {
        return $v >= 1e9 ? rtrim(rtrim(number_format($v / 1e9, 2, ',', '.'), '0'), ',') . ' tỷ' : number_format($v / 1e6, 0, ',', '.') . ' triệu';
    }
}
