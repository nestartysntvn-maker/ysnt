<?php
namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Growth since a fixed start date. Every series starts at 0 on that date and only counts what the system recorded
 * afterwards (orders, customers, connections, staff, candidates), so the curve proves growth instead of restating history.
 * Months are Vietnam months (UTC+7); the database stores UTC.
 */
class Growth
{
    public const TZ = 'Asia/Ho_Chi_Minh';
    public const VALID = "o.status NOT IN ('cancelled','returned')";
    public const PROVIDERS = ['zalo' => 'Zalo', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'web' => 'Web'];

    /** start date: the setting, else the month of the first order, else this month */
    public static function start(): Carbon
    {
        $s = (string) AppSetting::get('growth_start');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return Carbon::parse($s, self::TZ)->startOfDay();
        $first = DB::table('orders')->min('created_at');
        return ($first ? Carbon::parse($first, 'UTC')->tz(self::TZ) : now(self::TZ))->startOfMonth();
    }

    private static function startUtc(): string { return self::start()->copy()->tz('UTC')->toDateTimeString(); }

    /** @return string[] 'Y-m' from the start month to this month (at most 36) */
    public static function months(): array
    {
        $m = self::start()->copy()->startOfMonth(); $end = now(self::TZ)->startOfMonth(); $out = [];
        while ($m <= $end && count($out) < 36) { $out[] = $m->format('Y-m'); $m->addMonth(); }
        return $out;
    }

    public static function label(string $ym): string { return 'T' . (int) substr($ym, 5, 2) . '/' . substr($ym, 2, 2); }

    private static function ym(string $col): string { return "DATE_FORMAT(DATE_ADD($col, INTERVAL 7 HOUR), '%Y-%m')"; }

    /** align a [ym => value] map onto the month list */
    public static function series(array $byMonth): array { return array_map(fn ($m) => (int) round((float) ($byMonth[$m] ?? 0)), self::months()); }

    public static function cumulative(array $s): array { $run = 0; return array_map(function ($v) use (&$run) { return $run += $v; }, $s); }

    private static function grouped($q, string $col, string $agg = 'COUNT(*)'): array
    {
        return $q->where($col, '>=', self::startUtc())->selectRaw(self::ym($col) . " AS ym, $agg AS v")->groupBy('ym')->pluck('v', 'ym')->all();
    }

    /** @return array<string,int[]> revenue per channel group per month */
    public static function revenue(): array
    {
        $rows = DB::table('orders as o')->leftJoin('ambassadors as a', 'a.id', '=', 'o.ambassador_id')->whereRaw(self::VALID)->where('o.created_at', '>=', self::startUtc())
            ->selectRaw(self::ym('o.created_at') . ' AS ym, ' . Targets::groupSql() . ' AS g, SUM(o.total) AS v')->groupBy('ym', 'g')->get();
        $out = [];
        foreach (Targets::GROUPS as $g => $l) $out[$g] = self::series($rows->where('g', $g)->pluck('v', 'ym')->all());
        return $out;
    }

    public static function targets(): array
    {
        $rows = DB::table('sales_targets')->where('period', '>=', self::start()->copy()->startOfMonth()->toDateString())
            ->selectRaw("DATE_FORMAT(period, '%Y-%m') AS ym, SUM(target_amount) AS v")->groupBy('ym')->pluck('v', 'ym')->all();
        return self::series($rows);
    }

    public static function orders(): array { return self::series(self::grouped(DB::table('orders as o')->whereRaw(self::VALID), 'o.created_at')); }

    /** customers counted in the month of their first valid order */
    public static function newBuyers(): array
    {
        $first = DB::table('orders as o')->whereRaw(self::VALID)->whereNotNull('o.customer_id')->groupBy('o.customer_id')->selectRaw('o.customer_id, MIN(o.created_at) AS f');
        return self::series(self::grouped(DB::query()->fromSub($first, 'x'), 'x.f'));
    }

    /** @return array{buyers:int,repeat:int} since start */
    public static function repeat(): array
    {
        $per = DB::table('orders as o')->whereRaw(self::VALID)->whereNotNull('o.customer_id')->where('o.created_at', '>=', self::startUtc())->groupBy('o.customer_id')->selectRaw('COUNT(*) AS n');
        $r = DB::query()->fromSub($per, 'x')->selectRaw('COUNT(*) AS buyers, SUM(n >= 2) AS rep')->first();
        return ['buyers' => (int) ($r->buyers ?? 0), 'repeat' => (int) ($r->rep ?? 0)];
    }

    /** showroom orders that kept the buyer's phone (plan target 60%) */
    public static function contactKept(): array
    {
        $r = DB::table('orders as o')->whereRaw(self::VALID)->where('o.channel', 'showroom')->where('o.created_at', '>=', self::startUtc())
            ->selectRaw('COUNT(*) AS n, SUM(o.customer_id IS NOT NULL) AS kept')->first();
        return ['orders' => (int) ($r->n ?? 0), 'kept' => (int) ($r->kept ?? 0)];
    }

    /** @return array<string,int[]> new customer connections per channel per month */
    public static function connections(): array
    {
        $rows = DB::table('customer_identities')->where('created_at', '>=', self::startUtc())->selectRaw(self::ym('created_at') . ' AS ym, provider AS p, COUNT(*) AS v')->groupBy('ym', 'p')->get();
        $out = [];
        foreach (self::PROVIDERS as $p => $l) $out[$p] = self::series($rows->where('p', $p)->pluck('v', 'ym')->all());
        return $out;
    }

    public static function conversations(): array { return self::series(self::grouped(DB::table('conversations'), 'created_at')); }

    /** conversations the AI answered without a staff reply */
    public static function aiHandled(): array
    {
        $q = DB::table('conversations as c')->whereExists(fn ($e) => $e->from('conversation_messages')->whereColumn('conversation_id', 'c.id')->where('sender', 'ai'))
            ->whereNotExists(fn ($e) => $e->from('conversation_messages')->whereColumn('conversation_id', 'c.id')->where('sender', 'staff'));
        return self::series(self::grouped($q, 'c.created_at'));
    }

    public static function tourVisits(): array { return self::series(self::grouped(DB::table('tour_visits'), 'visited_at')); }
    public static function tourPax(): array { return self::series(self::grouped(DB::table('tour_visits'), 'visited_at', 'SUM(pax)')); }

    /** sales through internal staff referral codes */
    public static function staffSales(): array
    {
        $q = DB::table('orders as o')->join('ambassadors as a', 'a.id', '=', 'o.ambassador_id')->where('a.type', 'staff')->whereRaw(self::VALID);
        return self::series(self::grouped($q, 'o.created_at', 'SUM(o.total)'));
    }

    /** optional modules (HR, workplace, recruitment): empty series until their tables exist */
    public static function optional(string $table, string $col, ?\Closure $scope = null): array
    {
        if (! Schema::hasTable($table)) return self::series([]);
        $q = DB::table($table); if ($scope) $scope($q);
        return self::series(self::grouped($q, $col));
    }

    /** staff questions to the internal AI, demo accounts left out */
    public static function kbQuestions(): array
    {
        $demo = \App\Hr\Hr::demoIds();
        return self::optional('kb_questions', 'created_at', fn ($q) => $q->where(fn ($w) => $w->whereNull('employee_id')->orWhereNotIn('employee_id', $demo ?: [0])));
    }

    /** applications, minus ones a demo account referred */
    public static function applications(): array
    {
        $demo = \App\Hr\Hr::demoIds();
        return self::optional('job_applications', 'created_at', fn ($q) => $q->whereNull('deleted_at')->where(fn ($w) => $w->whereNull('referred_by_employee_id')->orWhereNotIn('referred_by_employee_id', $demo ?: [0])));
    }

    /** month-over-month text for a tile */
    public static function delta(array $s): string
    {
        $n = count($s); $cur = $n ? $s[$n - 1] : 0; $prev = $n > 1 ? $s[$n - 2] : 0;
        if ($n < 2) return 'Tháng đầu tiên';
        if ($prev === 0) return $cur > 0 ? 'Tháng trước 0, tháng này ' . number_format($cur, 0, ',', '.') : 'Chưa phát sinh';
        $pct = round(($cur - $prev) / $prev * 100);
        return ($pct >= 0 ? '+' : '') . $pct . '% so với tháng trước';
    }
}
