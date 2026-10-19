<?php

namespace App\Support\Revenue;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sums revenue_days for the dashboard strip and the Sales page. Comparisons default to the same
 * calendar dates a year earlier; pass another range (or false for none). When there's no data for
 * the comparison dates the change is null (a gap), never a made-up zero.
 */
class Report
{
    public const PERIODS = [
        'mtd' => 'Month to date',
        'last30' => 'Last 30 days',
        'last90' => 'Last 90 days',
        'ytd' => 'Year to date',
    ];

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function range(string $period): array
    {
        $t = self::today();

        return match ($period) {
            'last30' => [$t->subDays(29), $t],
            'last90' => [$t->subDays(89), $t],
            'ytd' => [$t->startOfYear(), $t],
            default => [$t->startOfMonth(), $t],
        };
    }

    /** Totals for a range: sessions, orders, revenue_cents. */
    public static function totals(CarbonImmutable $from, CarbonImmutable $to, ?int $brandId = null, ?string $channel = null): array
    {
        $q = DB::table('revenue_days')->whereBetween('day', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }
        if ($channel) {
            $q->where('channel', $channel);
        }
        $r = $q->selectRaw('count(*) as n, coalesce(sum(sessions),0) as sessions, coalesce(sum(orders),0) as orders, coalesce(sum(revenue_cents),0) as cents')->first();

        return ['rows' => (int) $r->n, 'sessions' => (int) $r->sessions, 'orders' => (int) $r->orders, 'cents' => (int) $r->cents];
    }

    /** The comparison dates: given, none (false), or by default the same dates last year. */
    private static function against(CarbonImmutable $from, CarbonImmutable $to, array|false|null $vs): ?array
    {
        return $vs === false ? null : ($vs ?? [$from->subYear(), $to->subYear()]);
    }

    /**
     * A set of figures with the comparison's alongside: revenue, orders, sessions, conversion rate,
     * average order. 'prev' holds the comparison's own figures.
     */
    public static function figures(CarbonImmutable $from, CarbonImmutable $to, ?int $brandId = null, array|false|null $vs = null): array
    {
        $now = self::totals($from, $to, $brandId);
        $cmp = self::against($from, $to, $vs);
        $ly = $cmp ? self::totals($cmp[0], $cmp[1], $brandId) : ['rows' => 0, 'sessions' => 0, 'orders' => 0, 'cents' => 0];
        $hasLy = $ly['rows'] > 0;

        $conv = fn ($t) => $t['sessions'] > 0 ? $t['orders'] / $t['sessions'] : null;
        $aov = fn ($t) => $t['orders'] > 0 ? $t['cents'] / $t['orders'] : null;

        return [
            'has_data' => $now['rows'] > 0,
            'cents' => $now['cents'],
            'orders' => $now['orders'],
            'sessions' => $now['sessions'],
            'conv' => $conv($now),
            'aov_cents' => $aov($now),
            'prev' => $hasLy ? ['cents' => $ly['cents'], 'orders' => $ly['orders'], 'sessions' => $ly['sessions'], 'conv' => $conv($ly), 'aov_cents' => $aov($ly)] : null,
            'vs' => [
                'cents' => $hasLy ? self::change($now['cents'], $ly['cents']) : null,
                'orders' => $hasLy ? self::change($now['orders'], $ly['orders']) : null,
                'sessions' => $hasLy ? self::change($now['sessions'], $ly['sessions']) : null,
                // Conversion rate: the change in percentage points.
                'conv' => $hasLy && $conv($now) !== null && $conv($ly) !== null ? ($conv($now) - $conv($ly)) * 100 : null,
                'aov' => $hasLy && $aov($now) !== null && $aov($ly) !== null ? self::change($aov($now), $aov($ly)) : null,
            ],
        ];
    }

    /** The dashboard strip: yesterday and month to date for all brands, and each brand's month. */
    public static function strip(): array
    {
        $t = self::today();
        $brands = Revenue::brands();

        return [
            'yesterday' => self::figures($t->subDay(), $t->subDay()),
            'mtd' => $mtd = self::figures($t->startOfMonth(), $t),
            'pace' => self::pace($mtd['cents']),
            'brands' => $brands->map(fn ($b) => ['brand' => $b] + self::figures($t->startOfMonth(), $t, $b->id))->values(),
        ];
    }

    /**
     * Revenue by channel for a range: each channel's figures, share, change on last year, and
     * revenue for each of the last eight weeks (Monday to Sunday, the current week so far last).
     */
    public static function channels(CarbonImmutable $from, CarbonImmutable $to, ?int $brandId = null, array|false|null $vs = null): array
    {
        $sum = function (CarbonImmutable $a, CarbonImmutable $b) use ($brandId) {
            $q = DB::table('revenue_days')->whereBetween('day', [$a->format('Y-m-d'), $b->format('Y-m-d')]);
            if ($brandId) {
                $q->where('brand_id', $brandId);
            }

            return $q->groupBy('channel')->selectRaw('channel, count(*) as n, sum(sessions) as sessions, sum(orders) as orders, sum(revenue_cents) as cents')
                ->get()->keyBy('channel');
        };
        $now = $sum($from, $to);
        $cmp = self::against($from, $to, $vs);
        $ly = $cmp ? $sum($cmp[0], $cmp[1]) : collect();
        $hasLy = $ly->sum('n') > 0;
        $total = max(1, (int) $now->sum('cents'));

        // The eight weeks ending with the range's last week.
        $weekStart = $to->min(self::today())->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(7);
        $wq = DB::table('revenue_days')->whereBetween('day', [$weekStart->format('Y-m-d'), $weekStart->addWeeks(8)->subDay()->format('Y-m-d')]);
        if ($brandId) {
            $wq->where('brand_id', $brandId);
        }
        $weekly = [];
        foreach ($wq->get(['day', 'channel', 'revenue_cents']) as $r) {
            $i = intdiv((int) $weekStart->diffInDays(CarbonImmutable::parse(substr((string) $r->day, 0, 10), config('app.timezone'))), 7);
            if ($i >= 0 && $i < 8) {
                $weekly[$r->channel][$i] = ($weekly[$r->channel][$i] ?? 0) + (int) $r->revenue_cents;
            }
        }

        $rows = [];
        foreach (config('revenue.channels') as $key => $label) {
            $n = $now[$key] ?? null;
            $l = $ly[$key] ?? null;
            if (! $n && ! $l) {
                continue;
            }
            $cents = (int) ($n->cents ?? 0);
            $orders = (int) ($n->orders ?? 0);
            $sessions = (int) ($n->sessions ?? 0);
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'cents' => $cents,
                'orders' => $orders,
                'sessions' => $sessions,
                'share' => $cents / $total,
                'conv' => $sessions > 0 ? $orders / $sessions : null,
                'prev_cents' => $hasLy ? (int) ($l->cents ?? 0) : null,
                'vs' => $hasLy && $l && (int) $l->cents > 0 ? self::change($cents, (int) $l->cents) : null,
                'weeks' => array_map(fn ($i) => $weekly[$key][$i] ?? 0, range(0, 7)),
            ];
        }
        usort($rows, fn ($a, $b) => $b['cents'] <=> $a['cents']);

        return ['rows' => $rows, 'has_ly' => $hasLy, 'week_start' => $weekStart];
    }

    public const METRICS = [
        'revenue' => ['Revenue', 'money'],
        'orders' => ['Orders', 'int'],
        'sessions' => ['Sessions', 'int'],
        'conv' => ['Conversion rate', 'pct'],
        'aov' => ['Average order', 'money'],
    ];

    /** Daily sums for a range: day => [sessions, orders, cents]. */
    public static function daily(CarbonImmutable $from, CarbonImmutable $to, ?int $brandId = null, ?string $channel = null): array
    {
        $q = DB::table('revenue_days')->whereBetween('day', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }
        if ($channel) {
            $q->where('channel', $channel);
        }
        $out = [];
        foreach ($q->groupBy('day')->selectRaw('day, sum(sessions) as s, sum(orders) as o, sum(revenue_cents) as c')->get() as $r) {
            $out[substr((string) $r->day, 0, 10)] = ['s' => (int) $r->s, 'o' => (int) $r->o, 'c' => (int) $r->c];
        }

        return $out;
    }

    /** Chart points for one measure across a picked range, with its comparison lined up. */
    public static function trend(\App\Support\DateRange $range, string $metric, ?int $brandId = null, ?string $channel = null): array
    {
        $now = self::daily($range->from, $range->to, $brandId, $channel);
        $cmp = $range->comparison();
        $then = $cmp ? self::daily($cmp[0], $cmp[1], $brandId, $channel) : null;
        $pick = fn (?array $rows, string $k) => $rows === null ? null : array_map(fn ($r) => $r[$k], $rows);
        $ratio = function (string $num, string $den) use ($range, $now, $then, $pick) {
            $n = $range->series($pick($now, $num), $pick($then, $num));
            $d = $range->series($pick($now, $den), $pick($then, $den));

            return array_map(fn ($p, $q) => array_merge($p, [
                'v' => $q['v'] > 0 ? $p['v'] / $q['v'] : null,
                'c' => $p['c'] !== null && $q['c'] > 0 ? $p['c'] / $q['c'] : null,
            ]), $n, $d);
        };

        return match ($metric) {
            'orders' => $range->series($pick($now, 'o'), $pick($then, 'o')),
            'sessions' => $range->series($pick($now, 's'), $pick($then, 's')),
            'conv' => $ratio('o', 's'),
            'aov' => $ratio('c', 'o'),
            default => $range->series($pick($now, 'c'), $pick($then, 'c')),
        };
    }

    /**
     * Progress toward this month's goal: the share of the goal reached and whether that's on pace for
     * the share of the month gone. $brandId null sums every brand's goal. Null when no goal is set.
     */
    public static function pace(int $cents, ?int $brandId = null): ?array
    {
        $t = self::today();
        $q = DB::table('revenue_goals')->where('month', $t->startOfMonth()->format('Y-m-d'));
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }
        $goal = (int) $q->sum('goal_cents');
        if ($goal <= 0) {
            return null;
        }
        $share = $cents / $goal;
        $expected = $t->day / $t->daysInMonth;

        return ['goal_cents' => $goal, 'share' => $share, 'expected' => $expected, 'on_pace' => $share >= $expected * 0.97];
    }

    private static function change(float|int $now, float|int $then): ?float
    {
        return $then > 0 ? ($now - $then) / $then * 100 : null;
    }

    public static function money(?int $cents, bool $exact = false): string
    {
        if ($cents === null) {
            return '—';
        }

        return '$'.number_format($cents / 100, $exact ? 2 : 0);
    }

    public static function pct(?float $v, int $decimals = 1): string
    {
        return $v === null ? '—' : number_format($v * 100, $decimals).'%';
    }
}
