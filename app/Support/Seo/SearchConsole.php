<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Support\Revenue\Ga4;
use Carbon\CarbonImmutable;
use App\Support\DateRange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Search Console per brand: the last 28 days against the 28 before, branded vs non-branded searches,
 * and the searches and pages gaining or losing the most clicks. Read-only; only the summary is cached.
 * Daily totals go into search_days, back as far as Search Console keeps them (16 months), so any
 * date range can be charted and compared; detail for other ranges is fetched when asked for.
 */
class SearchConsole
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public static function configured(): bool
    {
        return Ga4::credentials() !== null && config('search_console.sites') !== [];
    }

    public static function summary(string $slug): ?array
    {
        $s = Cache::get("seo:$slug");

        return is_array($s) ? $s : null;
    }

    public static function status(): array
    {
        $t = Cache::get('seo:last');

        return ['last' => is_numeric($t) ? CarbonImmutable::createFromTimestamp((int) $t, config('app.timezone')) : null, 'error' => Cache::get('seo:error')];
    }

    public static function forget(): void
    {
        foreach (array_keys(config('search_console.sites')) as $slug) {
            Cache::forget("seo:$slug");
            Cache::forget("seo:$slug:history");
        }
        foreach (['seo:last', 'seo:error', 'seo:refreshing'] as $k) {
            Cache::forget($k);
        }
    }

    public static function refreshIfStale(): void
    {
        if (! self::configured()) {
            return;
        }
        $last = Cache::get('seo:last');
        if (is_numeric($last) && now()->timestamp - (int) $last < config('search_console.refresh_minutes') * 60) {
            return;
        }
        if (! Cache::add('seo:refreshing', 1, 600)) {
            return;
        }
        dispatch(function () {
            try {
                self::refresh();
            } finally {
                Cache::forget('seo:refreshing');
            }
        })->afterResponse();
    }

    public static function refresh(): bool
    {
        try {
            $brands = self::brands();
            foreach (config('search_console.sites') as $slug => $site) {
                Cache::forever("seo:$slug", self::build($slug, $site));
                if ($brand = $brands->get($slug)) {
                    self::storeDays($brand->id, $slug, $site);
                }
            }
            Cache::forever('seo:last', now()->timestamp);
            Cache::forget('seo:error');

            return true;
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading Search Console failed ('.class_basename($e).').';
            Cache::forever('seo:error', $msg);
            Log::warning('Search Console sync failed', ['error' => $msg]);

            return false;
        }
    }

    /** The windows: [now from, now to, before from, before to]. */
    public static function windows(): array
    {
        $end = CarbonImmutable::now(config('app.timezone'))->startOfDay()->subDays(config('search_console.lag_days'));
        $n = config('search_console.window_days');

        return [$end->subDays($n - 1), $end, $end->subDays(2 * $n - 1), $end->subDays($n)];
    }

    private static function build(string $slug, string $site): array
    {
        [$a1, $a2, $b1, $b2] = self::windows();
        $detail = self::detail($slug, $site, $a1, $a2, $b1, $b2, $qa);

        return $detail + [
            'gap_rows' => self::gapRows($site, $a1, $a2, $qa, config("search_console.brand_terms.$slug", [])),
        ];
    }

    /**
     * Totals, branded clicks, movers and top searches and pages for one range against another (or
     * against nothing: then there are no movers). $qa gets this range's search rows for reuse.
     */
    private static function detail(string $slug, string $site, CarbonImmutable $a1, CarbonImmutable $a2, ?CarbonImmutable $b1, ?CarbonImmutable $b2, ?array &$qa = null): array
    {
        $qa = self::query($site, $a1, $a2, ['query'], 2000);
        $pa = self::query($site, $a1, $a2, ['page'], 1000);
        $qb = $b1 ? self::query($site, $b1, $b2, ['query'], 2000) : [];
        $pb = $b1 ? self::query($site, $b1, $b2, ['page'], 1000) : [];
        // One daily query covering both ranges; each range's totals are its days.
        $days = self::query($site, $b1 ? $b1->min($a1) : $a1, $b1 ? $b2->max($a2) : $a2, ['date'], 25000);
        $totals = fn ($from, $to) => self::totals(array_filter($days, fn ($r) => $r['keys'][0] >= $from->format('Y-m-d') && $r['keys'][0] <= $to->format('Y-m-d')));

        $terms = config("search_console.brand_terms.$slug", []);
        $branded = fn (array $rows) => array_sum(array_map(fn ($r) => self::isBranded($r['keys'][0], $terms) ? $r['clicks'] : 0, $rows));
        $top = fn (array $rows, ?callable $keep = null) => array_map(fn ($r) => ['key' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => round((float) $r['position'], 1)],
            array_slice(array_values(array_filter($rows, fn ($r) => ! $keep || $keep($r['keys'][0]))), 0, 10));

        return [
            'site' => $site,
            'from' => $a1->format('Y-m-d'),
            'to' => $a2->format('Y-m-d'),
            'now' => $totals($a1, $a2),
            'before' => $b1 ? $totals($b1, $b2) : null,
            'branded' => ['now' => (int) $branded($qa), 'before' => $b1 ? (int) $branded($qb) : null, 'has_terms' => $terms !== []],
            'queries' => $b1 ? self::movers($qa, $qb, fn ($k) => ! self::isBranded($k, $terms)) : null,
            'pages' => $b1 ? self::movers($pa, $pb) : null,
            'top_queries' => $top($qa, fn ($k) => ! self::isBranded($k, $terms)),
            'top_pages' => $top($pa),
        ];
    }

    /**
     * Detail for a picked range, fetched from Search Console when first asked for and kept for a few
     * hours. The default (last 28 days against the 28 before) comes from the regular refresh instead.
     */
    public static function forRange(string $slug, DateRange $range): ?array
    {
        $site = config("search_console.sites.$slug");
        if (! $site) {
            return null;
        }
        [$a1, $a2] = [$range->from, $range->to];
        $cmp = $range->comparison();
        $key = "seo:range:$slug:".$a1->format('Ymd').'-'.$a2->format('Ymd').':'.($cmp ? $cmp[0]->format('Ymd').'-'.$cmp[1]->format('Ymd') : 'none');
        if (is_array($hit = Cache::get($key))) {
            return $hit;
        }
        try {
            $d = self::detail($slug, $site, $a1, $a2, $cmp[0] ?? null, $cmp[1] ?? null);
            Cache::put($key, $d, now()->addHours(6));

            return $d;
        } catch (Throwable $e) {
            return ['error' => $e instanceof RuntimeException ? $e->getMessage() : 'Reading Search Console failed ('.class_basename($e).').'];
        }
    }

    /**
     * Daily totals into search_days: all 16 months Search Console keeps the first time, then the
     * last couple of weeks on each refresh (recent days are revised).
     */
    private static function storeDays(int $brandId, string $slug, string $site): void
    {
        $end = CarbonImmutable::now(config('app.timezone'))->startOfDay()->subDays(config('search_console.lag_days'));
        $loaded = Cache::get("seo:$slug:history") === $site;
        $from = $loaded ? $end->subDays(13) : $end->subMonths(config('search_console.history_months'))->addDay();
        $rows = self::query($site, $from, $end, ['date'], 25000);
        $now = now();
        DB::transaction(function () use ($brandId, $from, $end, $rows, $now) {
            DB::table('search_days')->where('brand_id', $brandId)->whereBetween('day', [$from->format('Y-m-d'), $end->format('Y-m-d')])->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('search_days')->insert(array_map(fn ($r) => [
                    'brand_id' => $brandId, 'day' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'],
                    'position_x_impressions' => $r['position'] * $r['impressions'], 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
        });
        Cache::forever("seo:$slug:history", $site);
    }

    /** Stored daily totals for a brand and range: day => [clicks, impressions, pos×impr]. */
    public static function days(int $brandId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        foreach (DB::table('search_days')->where('brand_id', $brandId)->whereBetween('day', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get() as $r) {
            $out[substr((string) $r->day, 0, 10)] = ['c' => (int) $r->clicks, 'i' => (int) $r->impressions, 'p' => (float) $r->position_x_impressions];
        }

        return $out;
    }

    /** Totals from stored days: clicks, impressions, CTR, average position; null with no days. */
    public static function sumDays(array $days): ?array
    {
        if ($days === []) {
            return null;
        }
        $c = array_sum(array_column($days, 'c'));
        $i = array_sum(array_column($days, 'i'));
        $p = array_sum(array_column($days, 'p'));

        return ['clicks' => $c, 'impressions' => $i, 'ctr' => $i > 0 ? $c / $i : null, 'position' => $i > 0 ? round($p / $i, 1) : null];
    }

    public static function firstDay(int $brandId): ?CarbonImmutable
    {
        $d = DB::table('search_days')->where('brand_id', $brandId)->min('day');

        return $d ? CarbonImmutable::parse(substr((string) $d, 0, 10), config('app.timezone')) : null;
    }

    /**
     * Non-branded searches with enough impressions to matter, each with the page that shows most for
     * it, for the Content gaps queue (which decides what each one needs). Search terms only.
     */
    private static function gapRows(string $site, CarbonImmutable $from, CarbonImmutable $to, array $queries, array $terms): array
    {
        $pages = [];
        foreach (self::query($site, $from, $to, ['query', 'page'], 5000) as $r) {
            $q = (string) $r['keys'][0];
            if (isset($r['keys'][1]) && ($pages[$q]['impressions'] ?? -1) < $r['impressions']) {
                $pages[$q] = ['page' => (string) $r['keys'][1], 'impressions' => $r['impressions']];
            }
        }
        $rows = [];
        foreach ($queries as $r) {
            $q = (string) $r['keys'][0];
            if ($r['impressions'] < config('search_console.gaps.min_impressions') || self::isBranded($q, $terms)) {
                continue;
            }
            $rows[] = ['query' => mb_substr($q, 0, 300), 'page' => $pages[$q]['page'] ?? null, 'clicks' => (int) $r['clicks'],
                'impressions' => (int) $r['impressions'], 'position' => round((float) $r['position'], 1)];
            if (count($rows) >= 400) {
                break;
            }
        }

        return $rows;
    }

    public static function isBranded(string $query, array $terms): bool
    {
        $q = mb_strtolower($query);
        foreach ($terms as $t) {
            if (str_contains($q, mb_strtolower($t))) {
                return true;
            }
        }

        return false;
    }

    /** Biggest gains and losses in clicks between the two windows (top 8 each). */
    private static function movers(array $now, array $before, ?callable $keep = null): array
    {
        $map = [];
        foreach ([[$now, 'now'], [$before, 'before']] as [$rows, $side]) {
            foreach ($rows as $r) {
                $k = (string) $r['keys'][0];
                if ($keep && ! $keep($k)) {
                    continue;
                }
                $map[$k] ??= ['key' => $k, 'now' => 0, 'before' => 0, 'position' => null, 'position_before' => null];
                $map[$k][$side] = (int) $r['clicks'];
                $map[$k][$side === 'now' ? 'position' : 'position_before'] = round((float) $r['position'], 1);
            }
        }
        $rows = array_map(fn ($m) => $m + ['change' => $m['now'] - $m['before']], array_values($map));
        $up = array_filter($rows, fn ($r) => $r['change'] > 0);
        $down = array_filter($rows, fn ($r) => $r['change'] < 0);
        usort($up, fn ($a, $b) => $b['change'] <=> $a['change']);
        usort($down, fn ($a, $b) => $a['change'] <=> $b['change']);

        return ['up' => array_slice($up, 0, 8), 'down' => array_slice($down, 0, 8)];
    }

    private static function totals(array $rows): array
    {
        $clicks = array_sum(array_column($rows, 'clicks'));
        $impr = array_sum(array_column($rows, 'impressions'));
        $pos = $impr > 0 ? array_sum(array_map(fn ($r) => $r['position'] * $r['impressions'], $rows)) / $impr : null;

        return ['clicks' => (int) $clicks, 'impressions' => (int) $impr, 'ctr' => $impr > 0 ? $clicks / $impr : null, 'position' => $pos !== null ? round($pos, 1) : null];
    }

    private static function query(string $site, CarbonImmutable $from, CarbonImmutable $to, array $dims, int $limit): array
    {
        $res = Http::withToken(Ga4::accessToken(self::SCOPE))->acceptJson()->timeout(config('search_console.timeout'))
            ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($site).'/searchAnalytics/query', [
                'startDate' => $from->format('Y-m-d'), 'endDate' => $to->format('Y-m-d'),
                'dimensions' => $dims, 'rowLimit' => $limit, 'dataState' => 'final',
            ]);
        if (! $res->successful()) {
            $why = collect($res->json('error.details') ?? [])->pluck('reason')->filter()->implode(' ').' '.$res->json('error.message');
            $email = Ga4::credentials()['client_email'] ?? 'the service account';
            $status = $res->status();
            throw new RuntimeException(match (true) {
                // The API itself is switched off in the service account's Google Cloud project.
                $status === 403 && (str_contains($why, 'SERVICE_DISABLED') || str_contains($why, 'has not been used in project') || str_contains($why, 'is disabled')) =>
                    'The Google Search Console API is turned off for the service account’s Google Cloud project (HTTP 403). In Google Cloud → APIs & Services → Library, open “Google Search Console API” and click Enable, then wait a few minutes.',
                $status === 403 => "Search Console refused {$email} for {$site} (HTTP 403). In Search Console, pick exactly this property ({$site}) → Settings → Users and permissions, and add that email as a Restricted user. A user added to a different property of the same site (with or without https/www) doesn’t count.",
                $status === 404 => "Search Console has no property {$site} (HTTP 404). Check SEARCH_CONSOLE_SITES; domain properties start with sc-domain:.",
                $status === 429 => 'Search Console quota reached for now (HTTP 429). It will try again later.',
                $status === 400 => "Search Console didn’t accept the property name {$site} (HTTP 400). Use sc-domain:example.com for a domain property, or the full address with its final slash (https://www.example.com/) for a URL-prefix property, exactly as Search Console’s property picker shows it.",
                default => "Search Console answered HTTP {$status} for {$site}.",
            });
        }

        return array_map(fn ($r) => ['keys' => $r['keys'] ?? [''], 'clicks' => (float) ($r['clicks'] ?? 0), 'impressions' => (float) ($r['impressions'] ?? 0), 'position' => (float) ($r['position'] ?? 0)],
            $res->json('rows') ?? []);
    }

    /** Configured brands, in brand order, keyed by slug. */
    public static function brands()
    {
        return Brand::whereIn('slug', array_keys(config('search_console.sites')))->orderBy('sort')->get()->keyBy('slug');
    }
}
