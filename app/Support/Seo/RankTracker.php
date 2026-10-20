<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Models\SeoKeyword;
use App\Models\SeoRankCheck;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Rank tracking: where each brand's site shows up in Google (desktop, the configured market) for the
 * searches we track. Checks go through DataForSEO's queue, which costs about a third of a live check:
 * posting is charged, collecting a few minutes later is free. Each search is checked every rank_days.
 */
class RankTracker
{
    private const IN_PROGRESS = [20100, 40601, 40602];

    /** Add searches for a brand (one per line or comma); returns how many were new. */
    public static function add(Brand $brand, array $keywords, ?int $userId): int
    {
        $have = SeoKeyword::where('brand_id', $brand->id)->count();
        $room = max(0, (int) config('dataforseo.max_keywords') - $have);
        $added = 0;
        foreach (array_unique(array_filter(array_map(fn ($k) => mb_substr(SeoKeyword::normalize((string) $k), 0, 200), $keywords))) as $k) {
            if ($added >= $room) {
                break;
            }
            $kw = SeoKeyword::firstOrCreate(['brand_id' => $brand->id, 'keyword' => $k], ['created_by' => $userId]);
            $added += $kw->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }

    public static function refreshIfStale(): void
    {
        if (! DataForSeo::configured() || ! SeoKeyword::query()->exists() || ! Cache::add('dfs:rank:busy', 1, 300)) {
            return;
        }
        dispatch(function () {
            try {
                self::run();
            } finally {
                Cache::forget('dfs:rank:busy');
            }
        })->afterResponse();
    }

    /** Collect finished checks, fill in volume and difficulty for new searches, then queue the ones due. */
    public static function run(): void
    {
        try {
            self::collect();
            self::fillMetrics();
            $due = SeoKeyword::whereNull('task_id')
                ->where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<', now()->subDays((int) config('dataforseo.rank_days'))))
                ->with('brand')->limit(500)->get();
            self::queue($due, null);
            Cache::forget('dfs:rank:error');
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Rank checks failed ('.class_basename($e).').';
            Cache::forever('dfs:rank:error', $msg);
            Log::warning('DataForSEO rank tracking failed', ['error' => $msg]);
        }
    }

    /** Queue a check for these searches now (each is charged). Returns how many were queued. */
    public static function queue(Collection $keywords, ?int $userId): int
    {
        $domains = SeoSuite::domains();
        $queued = 0;
        foreach ($keywords->filter(fn ($k) => isset($domains[$k->brand->slug]) && ! $k->task_id)->chunk(100) as $chunk) {
            $tasks = $chunk->map(fn (SeoKeyword $k) => [
                'keyword' => $k->keyword,
                'device' => 'desktop', 'os' => 'windows',
                'depth' => (int) config('dataforseo.rank_depth'),
                // DataForSEO bills by results page; stop once our site turns up.
                'stop_crawl_on_match' => [['match_value' => $domains[$k->brand->slug], 'match_type' => 'with_subdomains']],
                'find_targets_in' => ['organic'],
                'tag' => (string) $k->id,
            ] + SeoSuite::market())->values()->all();
            foreach (DataForSeo::postTasks('/v3/serp/google/organic/task_post', $tasks, $userId) as $t) {
                $kw = $chunk->firstWhere('id', (int) ($t['data']['tag'] ?? 0));
                if ($kw && isset($t['id']) && (int) ($t['status_code'] ?? 0) === 20100) {
                    $kw->update(['task_id' => (string) $t['id'], 'task_posted_at' => now()]);
                    $queued++;
                }
            }
        }

        return $queued;
    }

    /** Pick up finished checks (free). Ones stuck for two days are dropped so they're queued again. */
    public static function collect(): int
    {
        $domains = SeoSuite::domains();
        $done = 0;
        foreach (SeoKeyword::whereNotNull('task_id')->with('brand')->limit(200)->get() as $kw) {
            $task = DataForSeo::get('/v3/serp/google/organic/task_get/advanced/'.rawurlencode($kw->task_id));
            $code = (int) ($task['status_code'] ?? 0);
            if (in_array($code, self::IN_PROGRESS, true)) {
                if ($kw->task_posted_at?->lt(now()->subDays(2))) {
                    $kw->update(['task_id' => null]);
                }

                continue;
            }
            if ($code !== 20000 && $code !== 40501) {
                $kw->update(['task_id' => null, 'checked_at' => now()]);

                continue;
            }
            [$position, $url] = self::find($task['result'][0]['items'] ?? [], $domains[$kw->brand->slug] ?? '');
            self::record($kw, $position, $url);
            $done++;
        }

        return $done;
    }

    /** Our organic position (counting organic results only, as people do) and the page that ranks. */
    public static function find(array $items, string $domain): array
    {
        foreach ($items as $i) {
            $d = strtolower((string) ($i['domain'] ?? ''));
            if (($i['type'] ?? '') === 'organic' && $domain !== '' && ($d === $domain || str_ends_with($d, '.'.$domain))) {
                return [isset($i['rank_group']) ? (int) $i['rank_group'] : (isset($i['rank_absolute']) ? (int) $i['rank_absolute'] : null), $i['url'] ?? null];
            }
        }

        return [null, null];
    }

    public static function record(SeoKeyword $kw, ?int $position, ?string $url): void
    {
        $kw->update([
            'previous_position' => $kw->checked_at ? $kw->position : null,
            'position' => $position,
            'best_position' => $position && (! $kw->best_position || $position < $kw->best_position) ? $position : $kw->best_position,
            'url' => $url ? mb_substr($url, 0, 500) : null,
            'checked_at' => now(),
            'task_id' => null,
        ]);
        SeoRankCheck::updateOrCreate(['seo_keyword_id' => $kw->id, 'checked_on' => today()], ['position' => $position]);
    }

    /** Monthly searches and difficulty for searches that don't have them yet (one call for up to 700). */
    public static function fillMetrics(): void
    {
        $missing = SeoKeyword::whereNull('search_volume')->whereNull('difficulty')->limit(700)->get();
        if ($missing->isEmpty()) {
            return;
        }
        $r = DataForSeo::post('/v3/dataforseo_labs/google/keyword_overview/live', ['keywords' => $missing->pluck('keyword')->unique()->values()->all()] + SeoSuite::market());
        $found = collect($r['items'] ?? [])->filter(fn ($i) => isset($i['keyword']))->keyBy(fn ($i) => mb_strtolower($i['keyword']));
        foreach ($missing as $kw) {
            $info = isset($found[$kw->keyword]) ? SeoSuite::keywordInfo($found[$kw->keyword]) : null;
            // 0 marks "looked up, no data", so it isn't asked for again.
            $kw->update(['search_volume' => $info['volume'] ?? 0, 'difficulty' => $info['difficulty'] ?? null]);
        }
    }

    public static function error(): ?string
    {
        return Cache::get('dfs:rank:error');
    }
}
