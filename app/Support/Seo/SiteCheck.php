<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Models\SitePage;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs the Site check: reads the structured data on each listed page and checks each brand's product
 * feed. Runs once a day, by itself the first time someone opens the page after that.
 */
class SiteCheck
{
    public static function run(): void
    {
        $lock = Cache::lock('site-check:running', 900);
        if (! $lock->get()) {
            return;
        }
        try {
            self::pages(SitePage::all());
            self::feeds();
            Cache::forever('site-check:last', now()->timestamp);
        } finally {
            $lock->release();
        }
    }

    public static function refreshIfStale(): void
    {
        $last = Cache::get('site-check:last');
        if (is_numeric($last) && now()->timestamp - (int) $last < config('site_check.refresh_minutes') * 60) {
            return;
        }
        if (! SitePage::exists() && config('site_check.feeds') === []) {
            return;
        }
        dispatch(fn () => self::run())->afterResponse();
    }

    public static function last(): ?int
    {
        $t = Cache::get('site-check:last');

        return is_numeric($t) ? (int) $t : null;
    }

    /** Read each page and record what its structured data is missing. */
    public static function pages($pages): void
    {
        foreach ($pages->chunk(8) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => $chunk->map(fn ($p) => $pool->as((string) $p->id)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; OmniVEN site check; +'.config('app.url').')', 'Accept' => 'text/html'])
                ->connectTimeout(5)->timeout(config('site_check.page_timeout'))
                ->withOptions(['allow_redirects' => ['max' => 6]])
                ->get($p->url))->all());
            foreach ($chunk as $p) {
                $r = $responses[(string) $p->id] ?? null;
                if ($r instanceof Response && $r->successful()) {
                    $a = StructuredData::analyze($r->body(), $p->kind, $p->url);
                    $p->fill(['status' => $a['status'], 'issues' => $a['issues'], 'found' => $a['found'], 'error' => null]);
                } else {
                    $p->fill(['status' => 'error', 'issues' => [], 'found' => [],
                        'error' => $r instanceof Response ? "The page answered HTTP {$r->status()}." : 'Couldn’t load the page'.($r instanceof Throwable ? ' ('.Str::limit($r->getMessage(), 80).').' : '.')]);
                }
                $p->checked_at = now();
                $p->save();
            }
        }
    }

    /** Download and check each brand's product feed; keep counts only. */
    public static function feeds(): void
    {
        foreach (config('site_check.feeds') as $slug => $url) {
            $tmp = tempnam(sys_get_temp_dir(), 'feed');
            try {
                $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; OmniVEN feed check)'])
                    ->connectTimeout(10)->timeout(config('site_check.feed_timeout'))->sink($tmp)->get($url);
                if (! $res->successful()) {
                    throw new RuntimeException("The feed address answered HTTP {$res->status()}.".(in_array($res->status(), [401, 403], true) ? ' It may need to be made public, or use a scheduled-fetch address without a login.' : ''));
                }
                if (filesize($tmp) > config('site_check.feed_max_mb') * 1024 * 1024) {
                    throw new RuntimeException('The feed is over '.config('site_check.feed_max_mb').' MB; OmniVEN only checks smaller feeds.');
                }
                Cache::forever("site-check:feed:$slug", ['at' => now()->timestamp, 'error' => null] + ProductFeed::check($tmp));
            } catch (Throwable $e) {
                $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Couldn’t download the feed ('.Str::limit($e->getMessage(), 80).').';
                // Keep the last good numbers, marked with the failure.
                $prev = Cache::get("site-check:feed:$slug");
                Cache::forever("site-check:feed:$slug", ['error' => $msg] + (is_array($prev) ? $prev : ['at' => null, 'items' => null, 'issues' => [], 'availability' => []]));
                Log::warning('Feed check failed', ['brand' => $slug, 'error' => $msg]);
            } finally {
                @unlink($tmp);
            }
        }
    }

    /** brand slug => [brand, url, report|null] for each configured feed. */
    public static function feedReports(): array
    {
        $brands = Brand::whereIn('slug', array_keys(config('site_check.feeds')))->get()->keyBy('slug');
        $out = [];
        foreach (config('site_check.feeds') as $slug => $url) {
            $r = Cache::get("site-check:feed:$slug");
            $out[$slug] = ['brand' => $brands[$slug] ?? null, 'url' => $url, 'report' => is_array($r) ? $r : null];
        }

        return $out;
    }
}
