<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Support\Revenue\Ga4;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Search Console per brand: the last 28 days against the 28 before, branded vs non-branded searches,
 * and the searches and pages gaining or losing the most clicks. Read-only; only the summary is cached.
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
            foreach (config('search_console.sites') as $slug => $site) {
                Cache::forever("seo:$slug", self::build($slug, $site));
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
        $days = self::query($site, $b1, $a2, ['date'], 1000);
        $now = self::totals(array_filter($days, fn ($r) => $r['keys'][0] >= $a1->format('Y-m-d')));
        $before = self::totals(array_filter($days, fn ($r) => $r['keys'][0] < $a1->format('Y-m-d')));

        $qa = self::query($site, $a1, $a2, ['query'], 2000);
        $qb = self::query($site, $b1, $b2, ['query'], 2000);
        $pa = self::query($site, $a1, $a2, ['page'], 1000);
        $pb = self::query($site, $b1, $b2, ['page'], 1000);

        $terms = config("search_console.brand_terms.$slug", []);
        $branded = fn (array $rows) => array_sum(array_map(fn ($r) => self::isBranded($r['keys'][0], $terms) ? $r['clicks'] : 0, $rows));

        return [
            'site' => $site,
            'from' => $a1->format('Y-m-d'),
            'to' => $a2->format('Y-m-d'),
            'now' => $now,
            'before' => $before,
            'daily' => array_values(array_map(fn ($r) => ['day' => $r['keys'][0], 'clicks' => (int) $r['clicks']], array_filter($days, fn ($r) => $r['keys'][0] >= $a1->format('Y-m-d')))),
            'branded' => ['now' => (int) $branded($qa), 'before' => (int) $branded($qb), 'has_terms' => $terms !== []],
            'queries' => self::movers($qa, $qb, fn ($k) => ! self::isBranded($k, $terms)),
            'pages' => self::movers($pa, $pb),
            'gap_rows' => self::gapRows($site, $a1, $a2, $qa, $terms),
            'top_queries' => array_map(fn ($r) => ['key' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => round((float) $r['position'], 1)],
                array_slice(array_values(array_filter($qa, fn ($r) => ! self::isBranded($r['keys'][0], $terms))), 0, 10)),
        ];
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
            throw new RuntimeException(match ($res->status()) {
                403 => "Search Console refused the service account for {$site} (HTTP 403). In Search Console → Settings → Users and permissions, add the service account’s email as a Restricted user.",
                404 => "Search Console has no property {$site} (HTTP 404). Check SEARCH_CONSOLE_SITES; domain properties start with sc-domain:.",
                429 => 'Search Console quota reached for now (HTTP 429). It will try again later.',
                default => "Search Console answered HTTP {$res->status()} for {$site}.",
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
