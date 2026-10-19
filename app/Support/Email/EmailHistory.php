<?php

namespace App\Support\Email;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Daily email activity from Klaviyo's metric aggregates (read-only): emails received, opened and
 * clicked, bounces, unsubscribes, spam complaints, new subscribers, and orders, each split into
 * campaigns and flows. Kept in email_days so any date range can be shown and compared. Klaviyo
 * answers up to a year per request and keeps five years, so history is loaded a year per refresh.
 * Counts only: never profiles or email addresses.
 */
class EmailHistory
{
    /** Our name => [Klaviyo metric names to try, measurement, split]. Split "flow" separates flows from campaigns. */
    public const METRICS = [
        'received' => [['Received Email'], 'count', 'flow'],
        'opened' => [['Opened Email'], 'unique', 'flow'],
        'clicked' => [['Clicked Email'], 'unique', 'flow'],
        'bounced' => [['Bounced Email'], 'count', 'flow'],
        'unsubscribed' => [['Unsubscribed from Email Marketing', 'Unsubscribed'], 'count', 'flow'],
        'spam' => [['Marked Email as Spam'], 'count', 'flow'],
        'subscribed' => [['Subscribed to Email Marketing', 'Subscribed to List'], 'count', null],
    ];

    private const REACHED = 'klaviyo:history-to';
    private const ERROR = 'klaviyo:history-error';

    public static function configured(): bool
    {
        return Klaviyo::configured();
    }

    /** One refresh: the last two weeks again (late opens and orders), then one more year of history. */
    public static function refresh(bool $fullHistory = false): bool
    {
        try {
            $tz = config('app.timezone');
            $today = CarbonImmutable::now($tz)->startOfDay();
            $ids = self::metricIds();
            self::sync($ids, $today->subDays(13), $today);

            $floor = $today->subYears(config('klaviyo.history_years'))->addDay();
            $reached = Cache::get(self::REACHED);
            $reached = is_string($reached) ? CarbonImmutable::parse($reached, $tz) : $today->subDays(13);
            while ($reached->gt($floor)) {
                $start = $reached->subDays(360)->max($floor);
                self::sync($ids, $start, $reached->subDay());
                Cache::forever(self::REACHED, $start->format('Y-m-d'));
                $reached = $start;
                if (! $fullHistory) {
                    break;
                }
            }
            Cache::forget(self::ERROR);

            return true;
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading Klaviyo history failed ('.class_basename($e).').';
            Cache::forever(self::ERROR, $msg);
            Log::warning('Klaviyo history sync failed', ['error' => $msg]);

            return false;
        }
    }

    public static function status(): array
    {
        return ['history_from' => Cache::get(self::REACHED), 'error' => Cache::get(self::ERROR)];
    }

    public static function forget(): void
    {
        foreach ([self::REACHED, self::ERROR, 'klaviyo:metric-ids'] as $k) {
            Cache::forget($k);
        }
    }

    /** Our metric name => Klaviyo metric id, for the metrics this account has. Looked up once a day. */
    private static function metricIds(): array
    {
        $hit = Cache::get('klaviyo:metric-ids');
        if (is_array($hit)) {
            return $hit;
        }
        $byName = [];
        $url = config('klaviyo.url').'/metrics/';
        $query = ['fields[metric]' => 'name,integration'];
        for ($i = 0; $url && $i < 30; $i++) {
            $res = Klaviyo::check(Klaviyo::http()->get($url, $query));
            foreach ($res->json('data') ?? [] as $m) {
                $name = (string) ($m['attributes']['name'] ?? '');
                $integration = strtolower((string) ($m['attributes']['integration']['name'] ?? $m['attributes']['integration']['key'] ?? 'klaviyo'));
                // Klaviyo's own metric wins over a same-named one from another integration.
                if ($name !== '' && (! isset($byName[$name]) || $integration === 'klaviyo')) {
                    $byName[$name] = (string) $m['id'];
                }
            }
            $url = $res->json('links.next');
            $query = [];
        }
        $ids = [];
        foreach (self::METRICS as $key => [$names]) {
            foreach ($names as $n) {
                if (isset($byName[$n])) {
                    $ids[$key] = $byName[$n];
                    break;
                }
            }
        }
        Cache::put('klaviyo:metric-ids', $ids, now()->addDay());

        return $ids;
    }

    /** Re-read every metric for a range (at most a year) and replace those days. */
    private static function sync(array $ids, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = [];
        $put = function (string $metric, string $kind, array $byDay, string $field = 'count') use (&$rows) {
            foreach ($byDay as $day => $v) {
                $rows["$day|$metric|$kind"] ??= ['day' => $day, 'metric' => $metric, 'kind' => $kind, 'count' => 0, 'value_cents' => 0];
                $rows["$day|$metric|$kind"][$field] += $field === 'value_cents' ? (int) round($v * 100) : (int) round($v);
            }
        };

        foreach (self::METRICS as $key => [, $measure, $split]) {
            if (! isset($ids[$key])) {
                continue;
            }
            if ($split === 'flow') {
                // Grouped by flow: events with no flow are campaign sends.
                foreach (self::aggregate($ids[$key], [$measure], $from, $to, ['$flow']) as $g) {
                    $put($key, $g['dims'][0] === '' ? 'campaign' : 'flow', $g['m'][$measure]);
                }
            } else {
                foreach (self::aggregate($ids[$key], [$measure], $from, $to) as $g) {
                    $put($key, 'all', $g['m'][$measure]);
                }
            }
        }

        // Orders: all of them, those credited to a flow, and those credited to any email or SMS.
        $order = (string) config('klaviyo.order_metric_id');
        if ($order !== '') {
            foreach (self::aggregate($order, ['count', 'sum_value'], $from, $to) as $g) {
                $put('orders', 'all', $g['m']['count']);
                $put('orders', 'all', $g['m']['sum_value'], 'value_cents');
            }
            $attributed = ['count' => [], 'sum_value' => []];
            foreach (self::aggregate($order, ['count', 'sum_value'], $from, $to, ['$attributed_channel']) as $g) {
                if ($g['dims'][0] !== '') {
                    foreach (['count', 'sum_value'] as $m) {
                        foreach ($g['m'][$m] as $day => $v) {
                            $attributed[$m][$day] = ($attributed[$m][$day] ?? 0) + $v;
                        }
                    }
                }
            }
            $flow = ['count' => [], 'sum_value' => []];
            foreach (self::aggregate($order, ['count', 'sum_value'], $from, $to, ['$attributed_flow']) as $g) {
                if ($g['dims'][0] !== '') {
                    foreach (['count', 'sum_value'] as $m) {
                        foreach ($g['m'][$m] as $day => $v) {
                            $flow[$m][$day] = ($flow[$m][$day] ?? 0) + $v;
                        }
                    }
                }
            }
            $put('orders', 'flow', $flow['count']);
            $put('orders', 'flow', $flow['sum_value'], 'value_cents');
            // Campaigns: credited to email or SMS but not to a flow.
            $minus = fn (array $a, array $b) => array_map(fn ($day) => max(0, ($a[$day] ?? 0) - ($b[$day] ?? 0)), array_combine(array_keys($a), array_keys($a)));
            $put('orders', 'campaign', $minus($attributed['count'], $flow['count']));
            $put('orders', 'campaign', $minus($attributed['sum_value'], $flow['sum_value']), 'value_cents');
        }

        $now = now();
        $insert = array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now],
            array_values(array_filter($rows, fn ($r) => $r['count'] > 0 || $r['value_cents'] > 0)));
        DB::transaction(function () use ($from, $to, $insert) {
            DB::table('email_days')->whereBetween('day', [$from->format('Y-m-d'), $to->format('Y-m-d')])->delete();
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('email_days')->insert($chunk);
            }
        });
    }

    /**
     * Daily values for one metric, optionally grouped.
     *
     * @return list<array{dims: list<string>, m: array<string, array<string, float>>}>
     */
    private static function aggregate(string $metricId, array $measurements, CarbonImmutable $from, CarbonImmutable $to, array $by = []): array
    {
        $tz = config('app.timezone');
        $attributes = [
            'metric_id' => $metricId,
            'measurements' => $measurements,
            'interval' => 'day',
            'timezone' => $tz,
            'page_size' => 500,
            'filter' => [
                'greater-or-equal(datetime,'.$from->format('Y-m-d\TH:i:s').')',
                'less-than(datetime,'.$to->addDay()->format('Y-m-d\TH:i:s').')',
            ],
        ];
        if ($by) {
            $attributes['by'] = $by;
        }
        $res = Klaviyo::check(Klaviyo::http()->post(config('klaviyo.url').'/metric-aggregates/', ['data' => ['type' => 'metric-aggregate', 'attributes' => $attributes]]));
        $dates = array_map(fn ($d) => substr((string) $d, 0, 10), $res->json('data.attributes.dates') ?? []);
        $out = [];
        foreach ($res->json('data.attributes.data') ?? [] as $g) {
            $m = [];
            foreach ($measurements as $name) {
                $m[$name] = [];
                foreach ($g['measurements'][$name] ?? [] as $i => $v) {
                    if (isset($dates[$i]) && (float) $v != 0) {
                        $m[$name][$dates[$i]] = (float) $v;
                    }
                }
            }
            $out[] = ['dims' => array_map(fn ($d) => (string) ($d ?? ''), $g['dimensions'] ?? []) + [0 => ''], 'm' => $m];
        }

        return $out;
    }

    /** Stored days for a range: day => metric => kind => [count, value_cents]. */
    public static function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        foreach (DB::table('email_days')->whereBetween('day', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get() as $r) {
            $out[substr((string) $r->day, 0, 10)][$r->metric][$r->kind] = ['n' => (int) $r->count, 'v' => (int) $r->value_cents];
        }

        return $out;
    }

    /**
     * Totals for a range, for campaigns, flows and both: received, opened, clicked, bounced,
     * unsubscribed, spam, orders, revenue; plus new subscribers and every order. Null with no days.
     */
    public static function totals(array $days): ?array
    {
        if ($days === []) {
            return null;
        }
        $t = [];
        foreach (['campaign', 'flow', 'email'] as $k) {
            $t[$k] = array_fill_keys(['received', 'opened', 'clicked', 'bounced', 'unsubscribed', 'spam', 'orders', 'revenue_cents'], 0);
        }
        $t['subscribed'] = 0;
        $t['all_orders'] = 0;
        $t['all_revenue_cents'] = 0;
        foreach ($days as $metrics) {
            foreach ($metrics as $metric => $kinds) {
                foreach ($kinds as $kind => $v) {
                    if ($kind === 'all') {
                        if ($metric === 'subscribed') {
                            $t['subscribed'] += $v['n'];
                        } elseif ($metric === 'orders') {
                            $t['all_orders'] += $v['n'];
                            $t['all_revenue_cents'] += $v['v'];
                        }

                        continue;
                    }
                    foreach ([$kind, 'email'] as $k) {
                        $t[$k][$metric] += $v['n'];
                        if ($metric === 'orders') {
                            $t[$k]['revenue_cents'] += $v['v'];
                        }
                    }
                }
            }
        }
        foreach (['campaign', 'flow', 'email'] as $k) {
            $r = $t[$k]['received'];
            $t[$k] += [
                'open_rate' => $r > 0 ? $t[$k]['opened'] / $r : null,
                'click_rate' => $r > 0 ? $t[$k]['clicked'] / $r : null,
                'unsub_rate' => $r > 0 ? $t[$k]['unsubscribed'] / $r : null,
                'spam_rate' => $r > 0 ? $t[$k]['spam'] / $r : null,
                'rev_per_email_cents' => $r > 0 ? $t[$k]['revenue_cents'] / $r : null,
            ];
        }

        return $t;
    }

    public const CHART = [
        'received' => ['Emails received', 'int'],
        'open_rate' => ['Open rate', 'pct'],
        'click_rate' => ['Click rate', 'pct'],
        'orders' => ['Orders from email & SMS', 'int'],
        'revenue' => ['Revenue from email & SMS', 'money'],
        'unsubscribed' => ['Unsubscribes', 'int'],
        'subscribed' => ['New subscribers', 'int'],
    ];

    /** Chart points for one measure (emails, rates, orders, revenue…) across a range and its comparison. */
    public static function trend(DateRange $range, string $metric): array
    {
        $cmp = $range->comparison();
        $now = self::days($range->from, $range->to);
        $then = $cmp ? self::days($cmp[0], $cmp[1]) : null;
        // day => value for one stored metric, campaigns and flows together.
        $flat = function (?array $days, string $metric, string $field = 'n') {
            if ($days === null) {
                return null;
            }
            $out = [];
            foreach ($days as $day => $metrics) {
                foreach ($metrics[$metric] ?? [] as $kind => $v) {
                    if ($kind !== 'all' || $metric === 'subscribed') {
                        $out[$day] = ($out[$day] ?? 0) + $v[$field];
                    }
                }
            }

            return $out;
        };
        $ratio = function (string $num) use ($range, $now, $then, $flat) {
            $n = $range->series($flat($now, $num), $flat($then, $num));
            $d = $range->series($flat($now, 'received'), $flat($then, 'received'));

            return array_map(fn ($p, $q) => array_merge($p, [
                'v' => $q['v'] > 0 ? $p['v'] / $q['v'] : null,
                'c' => $p['c'] !== null && $q['c'] > 0 ? $p['c'] / $q['c'] : null,
            ]), $n, $d);
        };

        return match ($metric) {
            'open_rate' => $ratio('opened'),
            'click_rate' => $ratio('clicked'),
            'revenue' => $range->series($flat($now, 'orders', 'v'), $flat($then, 'orders', 'v')),
            'orders', 'unsubscribed', 'subscribed' => $range->series($flat($now, $metric), $flat($then, $metric)),
            default => $range->series($flat($now, 'received'), $flat($then, 'received')),
        };
    }

    public static function firstDay(): ?CarbonImmutable
    {
        $d = DB::table('email_days')->min('day');

        return $d ? CarbonImmutable::parse(substr((string) $d, 0, 10), config('app.timezone')) : null;
    }
}
