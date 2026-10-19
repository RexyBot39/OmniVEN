<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A date range picked on a report page (a preset or custom dates) and the range it's compared with:
 * the previous period of the same length, the same dates a year earlier, or nothing. Read from the
 * query string (?range=last30&compare=yoy, or ?range=custom&from=2025-01-01&to=2025-03-31) so every
 * view can be bookmarked and shared.
 */
class DateRange
{
    public const PRESETS = [
        'last7' => 'Last 7 days',
        'last28' => 'Last 28 days',
        'last30' => 'Last 30 days',
        'last90' => 'Last 90 days',
        'mtd' => 'Month to date',
        'last_month' => 'Last month',
        'qtd' => 'Quarter to date',
        'last_quarter' => 'Last quarter',
        'ytd' => 'Year to date',
        'last_year' => 'Last year',
        'last12m' => 'Last 12 months',
        'all' => 'All history',
        'custom' => 'Custom dates',
    ];

    public const COMPARES = [
        'previous' => 'Previous period',
        'yoy' => 'Same dates last year',
        'none' => 'No comparison',
    ];

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
        public readonly string $compare,
        public readonly CarbonImmutable $min,
        public readonly CarbonImmutable $max,
    ) {}

    /**
     * $max is the latest day the source has (today, or a few days back for Search Console); $min the
     * earliest it has (for "All history" and to keep custom dates sensible).
     */
    public static function fromRequest(Request $request, string $default = 'mtd', string $defaultCompare = 'yoy', ?CarbonImmutable $max = null, ?CarbonImmutable $min = null, array $presets = []): self
    {
        $tz = config('app.timezone');
        $max = ($max ?? CarbonImmutable::now($tz))->startOfDay();
        $min = ($min ?? $max->subYears(5))->startOfDay()->min($max);
        $allowed = $presets ?: array_keys(self::PRESETS);
        // "period" is the older name, kept so saved links still work.
        $asked = $request->query('range', $request->query('period'));
        $preset = in_array($asked, $allowed, true) ? (string) $asked : $default;
        $compare = array_key_exists((string) $request->query('compare'), self::COMPARES) ? (string) $request->query('compare') : $defaultCompare;

        if ($preset === 'custom') {
            $from = self::date($request->query('from'), $tz);
            $to = self::date($request->query('to'), $tz);
            if (! $from && ! $to) {
                $preset = $default;
            } else {
                $to ??= $max;
                $from ??= $to->subDays(29);
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                $to = $to->min($max)->max($min);
                $from = $from->max($min)->min($to);

                return new self($from, $to, 'custom', $compare, $min, $max);
            }
        }
        [$from, $to] = self::preset($preset, $max, $min);

        return new self($from->max($min)->min($max), $to->min($max), $preset, $compare, $min, $max);
    }

    /** A range without a request (for code that only needs the dates). */
    public static function make(CarbonImmutable $from, CarbonImmutable $to, string $compare = 'yoy'): self
    {
        return new self($from->startOfDay(), $to->startOfDay(), 'custom', $compare, $from->startOfDay(), $to->startOfDay());
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function preset(string $preset, CarbonImmutable $t, ?CarbonImmutable $min = null): array
    {
        return match ($preset) {
            'last7' => [$t->subDays(6), $t],
            'last28' => [$t->subDays(27), $t],
            'last30' => [$t->subDays(29), $t],
            'last90' => [$t->subDays(89), $t],
            'last_month' => [$t->subMonthNoOverflow()->startOfMonth(), $t->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'qtd' => [$t->startOfQuarter(), $t],
            'last_quarter' => [$t->subQuarterNoOverflow()->startOfQuarter(), $t->subQuarterNoOverflow()->endOfQuarter()->startOfDay()],
            'ytd' => [$t->startOfYear(), $t],
            'last_year' => [$t->subYear()->startOfYear(), $t->subYear()->endOfYear()->startOfDay()],
            'last12m' => [$t->subMonths(12)->addDay(), $t],
            'all' => [$min ?? $t->subYears(5), $t],
            default => [$t->startOfMonth(), $t],
        };
    }

    private static function date(mixed $v, string $tz): ?CarbonImmutable
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $v, $tz);
        } catch (\Throwable) {
            return null;
        }

        return $d && $d->format('Y-m-d') === $v ? $d : null;
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function hasComparison(): bool
    {
        return $this->compare !== 'none';
    }

    /**
     * The comparison range: the same number of days just before (previous), or the same calendar
     * dates a year earlier (yoy). Whole months and quarters compare with whole months and quarters.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function comparison(): ?array
    {
        if ($this->compare === 'yoy') {
            return [$this->from->subYearNoOverflow(), $this->to->subYearNoOverflow()];
        }
        if ($this->compare !== 'previous') {
            return null;
        }
        $wholeMonths = $this->from->isSameDay($this->from->startOfMonth()) && $this->to->isSameDay($this->to->endOfMonth()->startOfDay());
        if ($wholeMonths) {
            $months = ($this->to->year - $this->from->year) * 12 + $this->to->month - $this->from->month + 1;
            $start = $this->from->subMonthsNoOverflow($months);

            return [$start, $this->from->subDay()];
        }

        return [$this->from->subDays($this->days()), $this->from->subDay()];
    }

    public function label(): string
    {
        return $this->preset === 'custom' ? self::span($this->from, $this->to) : self::PRESETS[$this->preset];
    }

    public function compareLabel(): ?string
    {
        $c = $this->comparison();

        return $c ? self::span($c[0], $c[1]) : null;
    }

    public static function span(CarbonImmutable $a, CarbonImmutable $b): string
    {
        if ($a->isSameDay($b)) {
            return $a->format('M j, Y');
        }

        return $a->year === $b->year ? $a->format('M j').' – '.$b->format('M j, Y') : $a->format('M j, Y').' – '.$b->format('M j, Y');
    }

    /** Daily points up to ~2 months, weekly up to ~1.5 years, monthly beyond. */
    public function grain(): string
    {
        $d = $this->days();

        return $d <= 62 ? 'day' : ($d <= 550 ? 'week' : 'month');
    }

    /** The query string for this range, for links that keep it (merge in other filters). */
    public function query(array $extra = []): array
    {
        $q = ['range' => $this->preset, 'compare' => $this->compare];
        if ($this->preset === 'custom') {
            $q += ['from' => $this->from->format('Y-m-d'), 'to' => $this->to->format('Y-m-d')];
        }

        return array_filter(array_merge($q, $extra), fn ($v) => $v !== null && $v !== '');
    }

    /** Percent change, or null when there's nothing to compare with. */
    public static function change(float|int|null $now, float|int|null $then): ?float
    {
        return $now !== null && $then !== null && $then > 0 ? ($now - $then) / $then * 100 : null;
    }

    /**
     * Turn {Y-m-d => value} into points for a chart: one per day, week (Monday) or month across the
     * range, with the comparison range's values lined up point for point.
     *
     * @param  array<string, int|float>  $now
     * @param  array<string, int|float>|null  $then
     * @return list<array{label: string, start: string, v: int|float, c: int|float|null}>
     */
    public function series(array $now, ?array $then = null): array
    {
        $grain = $this->grain();
        $bucket = function (CarbonImmutable $d) use ($grain) {
            return match ($grain) {
                'week' => $d->startOfWeek(CarbonImmutable::MONDAY),
                'month' => $d->startOfMonth(),
                default => $d,
            };
        };
        $step = fn (CarbonImmutable $d) => match ($grain) {
            'week' => $d->addWeek(),
            'month' => $d->addMonthNoOverflow(),
            default => $d->addDay(),
        };
        $sum = function (array $values, CarbonImmutable $from, CarbonImmutable $to) use ($bucket) {
            $out = [];
            foreach ($values as $day => $v) {
                $d = CarbonImmutable::parse(substr((string) $day, 0, 10), config('app.timezone'));
                if ($d->lt($from) || $d->gt($to)) {
                    continue;
                }
                $k = $bucket($d)->format('Y-m-d');
                $out[$k] = ($out[$k] ?? 0) + $v;
            }

            return $out;
        };

        $a = $sum($now, $this->from, $this->to);
        $keys = [];
        for ($d = $bucket($this->from); $d->lte($this->to); $d = $step($d)) {
            $keys[] = $d;
        }
        $cmp = $this->comparison();
        $b = [];
        $bKeys = [];
        if ($cmp && $then !== null) {
            $b = $sum($then, $cmp[0], $cmp[1]);
            for ($d = $bucket($cmp[0]); $d->lte($cmp[1]); $d = $step($d)) {
                $bKeys[] = $d->format('Y-m-d');
            }
        }

        $points = [];
        foreach ($keys as $i => $d) {
            // A week or month cut off by the range's start or end holds fewer days: say so.
            $partial = $grain !== 'day' && ($d->lt($this->from) || $step($d)->subDay()->gt($this->to));
            $points[] = [
                'label' => match ($grain) {
                    'week' => 'Week of '.$d->max($this->from)->format('M j, Y'),
                    'month' => $d->format('M Y'),
                    default => $d->format('D M j, Y'),
                }.($partial ? ' (part: '.$d->max($this->from)->format('M j').' – '.$step($d)->subDay()->min($this->to)->format('M j').')' : ''),
                'partial' => $partial,
                'short' => match ($grain) {
                    'month' => $d->format($keys[0]->year === end($keys)->year ? 'M' : 'M y'),
                    default => $d->max($this->from)->format('M j'),
                },
                'start' => $d->format('Y-m-d'),
                'v' => $a[$d->format('Y-m-d')] ?? 0,
                'c' => isset($bKeys[$i]) ? ($b[$bKeys[$i]] ?? 0) : null,
            ];
        }

        return $points;
    }
}
