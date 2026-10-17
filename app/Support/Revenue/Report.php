<?php

namespace App\Support\Revenue;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sums revenue_days for the dashboard strip and the Sales page. Every comparison is with the same
 * calendar dates a year earlier; when there's no data for those dates the change is null (a gap).
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

    /** A set of figures with last year's alongside: revenue, orders, conversion rate, average order. */
    public static function figures(CarbonImmutable $from, CarbonImmutable $to, ?int $brandId = null): array
    {
        $now = self::totals($from, $to, $brandId);
        $ly = self::totals($from->subYear(), $to->subYear(), $brandId);
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
            'vs' => [
                'cents' => $hasLy ? self::change($now['cents'], $ly['cents']) : null,
                'orders' => $hasLy ? self::change($now['orders'], $ly['orders']) : null,
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
            'mtd' => self::figures($t->startOfMonth(), $t),
            'brands' => $brands->map(fn ($b) => ['brand' => $b] + self::figures($t->startOfMonth(), $t, $b->id))->values(),
        ];
    }

    /**
     * Revenue by channel for a range: each channel's figures, share, change on last year, and
     * revenue for each of the last eight weeks (Monday to Sunday, the current week so far last).
     */
    public static function channels(CarbonImmutable $from, CarbonImmutable $to, ?int $brandId = null): array
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
        $ly = $sum($from->subYear(), $to->subYear());
        $hasLy = $ly->sum('n') > 0;
        $total = max(1, (int) $now->sum('cents'));

        $weekStart = self::today()->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(7);
        $wq = DB::table('revenue_days')->where('day', '>=', $weekStart->format('Y-m-d'));
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
                'vs' => $hasLy && $l && (int) $l->cents > 0 ? self::change($cents, (int) $l->cents) : null,
                'weeks' => array_map(fn ($i) => $weekly[$key][$i] ?? 0, range(0, 7)),
            ];
        }
        usort($rows, fn ($a, $b) => $b['cents'] <=> $a['cents']);

        return ['rows' => $rows, 'has_ly' => $hasLy, 'week_start' => $weekStart];
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
