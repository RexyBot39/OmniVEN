<?php

namespace App\Support\Seo;

use App\Models\Brand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The SEO suite's reports per brand, all from DataForSEO: the overview (keywords we rank for, estimated
 * traffic and its monthly history, top keywords and pages), competitors, backlinks and AI visibility.
 * Each is cached and refreshes by itself after a set number of days; keyword research and keyword gaps
 * are looked up when someone asks and kept for a week.
 */
class SeoSuite
{
    public const KINDS = ['overview', 'competitors', 'backlinks', 'ai'];

    /** Brand slug => domain measured. */
    public static function domains(): array
    {
        if ($set = config('dataforseo.domains')) {
            return $set;
        }
        $seen = [];
        foreach (Brand::whereNotNull('site')->orderBy('sort')->get(['slug', 'site']) as $b) {
            $host = strtolower(preg_replace('/^www\./i', '', (string) parse_url($b->site, PHP_URL_HOST)));
            $path = trim((string) parse_url($b->site, PHP_URL_PATH), '/');
            if ($host !== '' && $path === '' && ! in_array($host, $seen, true)) {
                $seen[$b->slug] = $host;
            }
        }

        return $seen;
    }

    /** @return Collection<string, Brand> */
    public static function brands(): Collection
    {
        $domains = self::domains();

        return Brand::whereIn('slug', array_keys($domains))->orderBy('sort')->get()->keyBy('slug');
    }

    public static function domain(Brand $brand): ?string
    {
        return self::domains()[$brand->slug] ?? null;
    }

    /** What a page shows: the data (if any), when it was fetched, the last error and whether a refresh is running. */
    public static function report(string $kind, Brand $brand): array
    {
        $c = Cache::get("dfs:$kind:{$brand->slug}");

        return [
            'data' => is_array($c) ? $c['data'] : null,
            'at' => is_array($c) ? CarbonImmutable::createFromTimestamp($c['at'], config('app.timezone')) : null,
            'error' => Cache::get("dfs:$kind:{$brand->slug}:error"),
            'loading' => Cache::has("dfs:$kind:{$brand->slug}:busy"),
        ];
    }

    public static function refreshIfStale(string $kind, Brand $brand, bool $force = false): void
    {
        if (! DataForSeo::configured() || ($kind === 'backlinks' && ! DataForSeo::backlinks())) {
            return;
        }
        $c = Cache::get("dfs:$kind:{$brand->slug}");
        // A failed refresh waits an hour before trying again, so a bad key doesn't spend on every page view.
        $failedAt = Cache::get("dfs:$kind:{$brand->slug}:failed");
        $fresh = is_array($c) && now()->timestamp - $c['at'] < config("dataforseo.{$kind}_days") * 86400;
        if (! $force && ($fresh || (is_numeric($failedAt) && now()->timestamp - $failedAt < 3600))) {
            return;
        }
        if (! Cache::add("dfs:$kind:{$brand->slug}:busy", 1, 600)) {
            return;
        }
        $slug = $brand->slug;
        dispatch(function () use ($kind, $slug) {
            try {
                if ($b = Brand::where('slug', $slug)->first()) {
                    self::refresh($kind, $b);
                }
            } finally {
                Cache::forget("dfs:$kind:$slug:busy");
            }
        })->afterResponse();
    }

    public static function refresh(string $kind, Brand $brand): bool
    {
        $key = "dfs:$kind:{$brand->slug}";
        try {
            $domain = self::domain($brand) ?? throw new RuntimeException('No domain is set for '.$brand->name.'.');
            $data = match ($kind) {
                'overview' => self::buildOverview($domain),
                'competitors' => self::buildCompetitors($domain),
                'backlinks' => self::buildBacklinks($domain),
                'ai' => self::buildAi($brand, $domain),
            };
            Cache::forever($key, ['at' => now()->timestamp, 'data' => $data]);
            Cache::forget("$key:error");
            Cache::forget("$key:failed");

            return true;
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading DataForSEO failed ('.class_basename($e).').';
            Cache::forever("$key:error", $msg);
            Cache::forever("$key:failed", now()->timestamp);
            Log::warning('DataForSEO refresh failed', ['kind' => $kind, 'brand' => $brand->slug, 'error' => $msg]);

            return false;
        }
    }

    public static function market(): array
    {
        return ['location_code' => (int) config('dataforseo.location_code'), 'language_code' => (string) config('dataforseo.language_code')];
    }

    // ---- Overview -----------------------------------------------------------------------------------------

    private static function buildOverview(string $domain): array
    {
        $now = DataForSeo::post('/v3/dataforseo_labs/google/domain_rank_overview/live', ['target' => $domain] + self::market());
        $history = DataForSeo::post('/v3/dataforseo_labs/google/historical_rank_overview/live', ['target' => $domain, 'date_from' => '2020-10-01'] + self::market());
        $ranked = DataForSeo::post('/v3/dataforseo_labs/google/ranked_keywords/live', ['target' => $domain, 'limit' => 100, 'item_types' => ['organic'],
            'order_by' => ['ranked_serp_element.serp_item.etv,desc']] + self::market());
        $pages = DataForSeo::post('/v3/dataforseo_labs/google/relevant_pages/live', ['target' => $domain, 'limit' => 20,
            'order_by' => ['metrics.organic.etv,desc']] + self::market());

        $months = collect($history['items'] ?? [])
            ->filter(fn ($i) => isset($i['year'], $i['month']))
            ->map(fn ($i) => ['month' => sprintf('%04d-%02d', $i['year'], $i['month'])] + self::organic($i['metrics']['organic'] ?? []))
            ->sortBy('month')->values()->all();

        return [
            'domain' => $domain,
            'now' => self::organic($now['items'][0]['metrics']['organic'] ?? []),
            'months' => $months,
            'total_keywords' => $ranked['total_count'] ?? null,
            'keywords' => array_values(array_filter(array_map(fn ($i) => self::rankedRow($i), $ranked['items'] ?? []))),
            'pages' => array_values(array_filter(array_map(fn ($i) => isset($i['page_address']) ? [
                'url' => (string) $i['page_address'],
                'traffic' => (float) ($i['metrics']['organic']['etv'] ?? 0),
                'keywords' => (int) ($i['metrics']['organic']['count'] ?? 0),
            ] : null, $pages['items'] ?? []))),
        ];
    }

    /** DataForSEO's organic metrics block, as the tiles and chart use it. */
    public static function organic(array $o): array
    {
        $n = fn (string $k) => (int) ($o[$k] ?? 0);
        $deep = 0;
        foreach (['pos_21_30', 'pos_31_40', 'pos_41_50', 'pos_51_60', 'pos_61_70', 'pos_71_80', 'pos_81_90', 'pos_91_100'] as $k) {
            $deep += $n($k);
        }

        return [
            'keywords' => $n('count'),
            'traffic' => round((float) ($o['etv'] ?? 0)),
            'value' => round((float) ($o['estimated_paid_traffic_cost'] ?? 0)),
            'top3' => $n('pos_1') + $n('pos_2_3'),
            'top10' => $n('pos_1') + $n('pos_2_3') + $n('pos_4_10'),
            'p11_20' => $n('pos_11_20'),
            'p21_100' => $deep,
            'new' => $n('is_new'),
            'up' => $n('is_up'),
            'down' => $n('is_down'),
            'lost' => $n('is_lost'),
        ];
    }

    private static function rankedRow(array $i): ?array
    {
        $kd = $i['keyword_data'] ?? [];
        $serp = $i['ranked_serp_element']['serp_item'] ?? [];
        if (! isset($kd['keyword'])) {
            return null;
        }

        return self::keywordInfo($kd) + [
            'position' => $serp['rank_group'] ?? $serp['rank_absolute'] ?? null,
            'url' => $serp['url'] ?? null,
            'traffic' => round((float) ($serp['etv'] ?? 0), 1),
        ];
    }

    /** Volume, difficulty, cost per click, intent and the last 12 months from a keyword_data block. */
    public static function keywordInfo(array $kd): array
    {
        $info = $kd['keyword_info'] ?? [];
        $trend = collect($info['monthly_searches'] ?? [])
            ->filter(fn ($m) => isset($m['year'], $m['month']))
            ->sortBy(fn ($m) => $m['year'] * 100 + $m['month'])
            ->take(-12)->map(fn ($m) => (int) ($m['search_volume'] ?? 0))->values()->all();

        return [
            'keyword' => (string) $kd['keyword'],
            'volume' => isset($info['search_volume']) ? (int) $info['search_volume'] : null,
            'difficulty' => isset($kd['keyword_properties']['keyword_difficulty']) ? (int) $kd['keyword_properties']['keyword_difficulty'] : null,
            'cpc' => isset($info['cpc']) ? round((float) $info['cpc'], 2) : null,
            'intent' => $kd['search_intent_info']['main_intent'] ?? null,
            'trend' => $trend,
        ];
    }

    // ---- Keyword research (on demand) --------------------------------------------------------------------

    public const RESEARCH_MODES = [
        'ideas' => ['Ideas', 'Searches on the same topic, even without your words'],
        'suggestions' => ['Contains', 'Longer searches that contain your words'],
        'related' => ['Related', 'What Google lists as “related searches”'],
    ];

    /** Up to 100 keywords for a seed, cached a week. Returns rows (best first) or throws with a plain message. */
    public static function research(string $seed, string $mode, ?int $userId): array
    {
        $seed = mb_strtolower(trim($seed));

        return Cache::remember('dfs:kw:'.md5($mode.'|'.$seed.'|'.json_encode(self::market())), now()->addDays(7), function () use ($seed, $mode, $userId) {
            $base = self::market() + ['limit' => 100];
            $result = match ($mode) {
                'suggestions' => DataForSeo::post('/v3/dataforseo_labs/google/keyword_suggestions/live', $base + ['keyword' => $seed, 'include_seed_keyword' => true, 'order_by' => ['keyword_info.search_volume,desc']], $userId),
                'related' => DataForSeo::post('/v3/dataforseo_labs/google/related_keywords/live', $base + ['keyword' => $seed, 'depth' => 2, 'order_by' => ['keyword_data.keyword_info.search_volume,desc']], $userId),
                default => DataForSeo::post('/v3/dataforseo_labs/google/keyword_ideas/live', $base + ['keywords' => [$seed], 'order_by' => ['keyword_info.search_volume,desc']], $userId),
            };
            $rows = [];
            foreach ($result['items'] ?? [] as $i) {
                $kd = $mode === 'related' ? ($i['keyword_data'] ?? []) : $i;
                if (isset($kd['keyword']) && ! isset($rows[$kd['keyword']])) {
                    $rows[$kd['keyword']] = self::keywordInfo($kd);
                }
            }

            return array_values($rows);
        });
    }

    // ---- Competitors --------------------------------------------------------------------------------------

    private static function buildCompetitors(string $domain): array
    {
        $r = DataForSeo::post('/v3/dataforseo_labs/google/competitors_domain/live', ['target' => $domain, 'limit' => 16, 'exclude_top_domains' => true,
            'item_types' => ['organic']] + self::market());

        return array_values(array_filter(array_map(fn ($i) => isset($i['domain']) && $i['domain'] !== $domain ? [
            'domain' => (string) $i['domain'],
            'shared' => (int) ($i['intersections'] ?? 0),
            'avg_position' => isset($i['avg_position']) ? round((float) $i['avg_position'], 1) : null,
            'keywords' => (int) ($i['full_domain_metrics']['organic']['count'] ?? 0),
            'traffic' => round((float) ($i['full_domain_metrics']['organic']['etv'] ?? 0)),
        ] : null, $r['items'] ?? [])));
    }

    /** Searches a competitor ranks for and we don't, biggest first. Cached a week. */
    public static function gap(Brand $brand, string $competitor, ?int $userId): array
    {
        $domain = self::domain($brand) ?? throw new RuntimeException('No domain is set for '.$brand->name.'.');
        $competitor = self::cleanDomain($competitor) ?? throw new RuntimeException('That doesn’t look like a website address.');

        return Cache::remember('dfs:gap:'.md5($domain.'|'.$competitor.'|'.json_encode(self::market())), now()->addDays(7), function () use ($domain, $competitor, $userId) {
            $r = DataForSeo::post('/v3/dataforseo_labs/google/domain_intersection/live', ['target_1' => $competitor, 'target_2' => $domain, 'intersections' => false,
                'item_types' => ['organic'], 'limit' => 100, 'order_by' => ['keyword_data.keyword_info.search_volume,desc']] + self::market(), $userId);

            return array_values(array_filter(array_map(function ($i) {
                if (! isset($i['keyword_data']['keyword'])) {
                    return null;
                }
                $them = $i['first_domain_serp_element'] ?? [];

                return self::keywordInfo($i['keyword_data']) + ['their_position' => $them['rank_group'] ?? $them['rank_absolute'] ?? null, 'their_url' => $them['url'] ?? null];
            }, $r['items'] ?? [])));
        });
    }

    public static function cleanDomain(string $value): ?string
    {
        $value = strtolower(trim($value));
        $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST);
        $host = is_string($host) ? preg_replace('/^www\./', '', $host) : null;

        return $host && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host) ? $host : null;
    }

    // ---- Backlinks ---------------------------------------------------------------------------------------

    private static function buildBacklinks(string $domain): array
    {
        $common = ['target' => $domain, 'rank_scale' => 'one_hundred'];
        $summary = DataForSeo::post('/v3/backlinks/summary/live', $common + ['include_subdomains' => true, 'include_indirect_links' => true,
            'exclude_internal_backlinks' => true, 'backlinks_status_type' => 'live']);
        $history = DataForSeo::post('/v3/backlinks/history/live', $common + ['date_from' => '2019-01-01', 'date_to' => now()->format('Y-m-d')]);
        $domains = DataForSeo::post('/v3/backlinks/referring_domains/live', $common + ['limit' => 50, 'order_by' => ['rank,desc'],
            'exclude_internal_backlinks' => true, 'backlinks_status_type' => 'live']);
        $link = fn (array $i) => isset($i['url_from']) ? [
            'from' => (string) $i['url_from'], 'domain' => (string) ($i['domain_from'] ?? ''), 'to' => (string) ($i['url_to'] ?? ''),
            'anchor' => mb_substr((string) ($i['anchor'] ?? ''), 0, 120), 'dofollow' => (bool) ($i['dofollow'] ?? false),
            'rank' => (int) ($i['domain_from_rank'] ?? $i['rank'] ?? 0), 'first_seen' => isset($i['first_seen']) ? substr((string) $i['first_seen'], 0, 10) : null,
            'lost' => isset($i['lost_date']) ? substr((string) $i['lost_date'], 0, 10) : null,
        ] : null;
        $new = DataForSeo::post('/v3/backlinks/backlinks/live', $common + ['mode' => 'one_per_domain', 'limit' => 30, 'order_by' => ['first_seen,desc'],
            'exclude_internal_backlinks' => true, 'backlinks_status_type' => 'live']);
        $lost = DataForSeo::post('/v3/backlinks/backlinks/live', $common + ['mode' => 'one_per_domain', 'limit' => 30, 'order_by' => ['rank,desc'],
            'exclude_internal_backlinks' => true, 'backlinks_status_type' => 'lost']);

        return [
            'summary' => [
                'rank' => (int) ($summary['rank'] ?? 0),
                'backlinks' => (int) ($summary['backlinks'] ?? 0),
                'domains' => (int) ($summary['referring_domains'] ?? 0),
                'dofollow_domains' => max(0, (int) ($summary['referring_domains'] ?? 0) - (int) ($summary['referring_domains_nofollow'] ?? 0)),
                'broken' => (int) ($summary['broken_backlinks'] ?? 0),
                'spam' => (int) ($summary['info']['target_spam_score'] ?? $summary['backlinks_spam_score'] ?? 0),
            ],
            'months' => collect($history['items'] ?? [])->filter(fn ($i) => isset($i['date']))->map(fn ($i) => [
                'month' => substr((string) $i['date'], 0, 7),
                'backlinks' => (int) ($i['backlinks'] ?? 0), 'domains' => (int) ($i['referring_domains'] ?? 0), 'rank' => (int) ($i['rank'] ?? 0),
                'new_domains' => (int) ($i['new_referring_domains'] ?? 0), 'lost_domains' => (int) ($i['lost_referring_domains'] ?? 0),
            ])->sortBy('month')->values()->all(),
            'domains' => array_values(array_filter(array_map(fn ($i) => isset($i['domain']) ? [
                'domain' => (string) $i['domain'], 'rank' => (int) ($i['rank'] ?? 0), 'backlinks' => (int) ($i['backlinks'] ?? 0),
                'first_seen' => isset($i['first_seen']) ? substr((string) $i['first_seen'], 0, 10) : null, 'spam' => (int) ($i['backlinks_spam_score'] ?? 0),
            ] : null, $domains['items'] ?? []))),
            'new' => array_values(array_filter(array_map($link, $new['items'] ?? []))),
            'lost' => array_values(array_filter(array_map($link, $lost['items'] ?? []))),
        ];
    }

    // ---- AI visibility -----------------------------------------------------------------------------------

    public const AI_PLATFORMS = ['google' => 'Google AI Overviews', 'chat_gpt' => 'ChatGPT'];

    private static function buildAi(Brand $brand, string $domain): array
    {
        $us = ['location_code' => 2840, 'language_code' => 'en'];
        $target = [['domain' => $domain, 'include_subdomains' => true]];
        $platforms = [];
        foreach (self::AI_PLATFORMS as $platform => $label) {
            // ChatGPT data exists for the United States in English only.
            $market = $platform === 'chat_gpt' ? $us : self::market();
            $r = DataForSeo::post('/v3/ai_optimization/llm_mentions/aggregated_metrics/live', ['target' => $target, 'platform' => $platform, 'internal_list_limit' => 10] + $market);
            $g = collect($r['total']['platform'] ?? [])->firstWhere('key', $platform) ?? [];
            $platforms[$platform] = ['mentions' => (int) ($g['mentions'] ?? 0), 'volume' => (int) ($g['ai_search_volume'] ?? 0), 'impressions' => (int) ($g['impressions'] ?? 0)];
        }

        $q = DataForSeo::post('/v3/ai_optimization/llm_mentions/search/live', ['target' => $target, 'platform' => 'google', 'limit' => 25,
            'order_by' => ['ai_search_volume,desc']] + self::market());
        $questions = array_values(array_filter(array_map(fn ($i) => isset($i['question']) ? [
            'question' => mb_substr((string) $i['question'], 0, 300),
            'answer' => mb_substr(trim(strip_tags((string) ($i['answer'] ?? ''))), 0, 600),
            'volume' => (int) ($i['ai_search_volume'] ?? 0),
            'sources' => array_slice(array_values(array_filter(array_map(fn ($s) => isset($s['url']) ? ['url' => (string) $s['url'], 'domain' => (string) ($s['domain'] ?? ''), 'title' => mb_substr((string) ($s['title'] ?? ''), 0, 140)] : null, $i['sources'] ?? []))), 0, 6),
            'seen' => isset($i['last_response_at']) ? substr((string) $i['last_response_at'], 0, 10) : null,
        ] : null, $q['items'] ?? [])));

        // Share of voice against the top competitors (from the Competitors report when it's in).
        $rivals = array_slice(array_column(self::report('competitors', $brand)['data'] ?? [], 'domain'), 0, 5);
        $share = [];
        if ($rivals) {
            $groups = array_map(fn ($d) => ['aggregation_key' => $d, 'target' => [['domain' => $d, 'include_subdomains' => true]]], array_merge([$domain], $rivals));
            $r = DataForSeo::post('/v3/ai_optimization/llm_mentions/cross_aggregated_metrics/live', ['targets' => $groups, 'platform' => 'google', 'internal_list_limit' => 5] + self::market());
            foreach ($r['items'] ?? [] as $i) {
                $g = collect($i['platform'] ?? [])->firstWhere('key', 'google') ?? [];
                if (isset($i['key'])) {
                    $share[] = ['domain' => (string) $i['key'], 'mentions' => (int) ($g['mentions'] ?? 0), 'us' => $i['key'] === $domain];
                }
            }
            usort($share, fn ($a, $b) => $b['mentions'] <=> $a['mentions']);
        }

        return ['platforms' => $platforms, 'questions' => $questions, 'share' => $share];
    }

    /** Admin footer line: this month's spend against the budget. */
    public static function spend(): array
    {
        return ['spent' => DataForSeo::spentThisMonth(), 'budget' => DataForSeo::budget()];
    }

    /** Keyword difficulty (0–100) as a word and a class: easy, medium or hard to reach the top 10. */
    public static function difficulty(?int $kd): array
    {
        return match (true) {
            $kd === null => ['—', ''],
            $kd < 30 => ['Easy', 'kd-easy'],
            $kd < 60 => ['Medium', 'kd-mid'],
            default => ['Hard', 'kd-hard'],
        };
    }

    /** What the person searching wants, in plain words. */
    public static function intent(?string $intent): ?string
    {
        return ['informational' => 'Learning', 'navigational' => 'Looking for a site', 'commercial' => 'Comparing', 'transactional' => 'Ready to buy'][$intent ?? ''] ?? null;
    }
}
