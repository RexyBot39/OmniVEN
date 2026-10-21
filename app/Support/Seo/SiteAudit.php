<?php

namespace App\Support\Seo;

use App\Models\Brand;
use App\Models\SeoAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Site audit: DataForSEO crawls a brand's site (up to audit_pages pages) and scores every page. We keep the
 * site's score, how many pages have each problem, and the 100 pages that need the most work.
 */
class SiteAudit
{
    /** [label, severity (error|warning|notice), what to do]. Keys are DataForSEO's page checks. */
    public const ISSUES = [
        'broken_links' => ['Broken links on the page', 'error', 'Fix or remove links to pages that no longer exist.'],
        'is_4xx_code' => ['Pages that return “not found” (4xx)', 'error', 'Restore the page or redirect it, and update links pointing at it.'],
        'is_5xx_code' => ['Server errors (5xx)', 'error', 'Ask the developer to check the server logs for these pages.'],
        'no_title' => ['No page title', 'error', 'Give every page a unique title of 30–60 characters with its main search.'],
        'duplicate_title' => ['Titles used on more than one page', 'warning', 'Make each title unique to its page.'],
        'no_description' => ['No meta description', 'warning', 'Write a 120–160 character description that makes people want to click.'],
        'duplicate_description' => ['Descriptions used on more than one page', 'warning', 'Write a unique description for each page.'],
        'title_too_long' => ['Title too long', 'warning', 'Keep titles under about 60 characters so Google shows them in full.'],
        'title_too_short' => ['Title too short', 'notice', 'Use the room: 30–60 characters, with the main search first.'],
        'no_h1_tag' => ['No H1 heading', 'warning', 'Add one H1 that says what the page is about.'],
        'duplicate_content' => ['Near-duplicate pages', 'warning', 'Merge them, or point one at the other with a canonical tag.'],
        'no_image_alt' => ['Images without alt text', 'notice', 'Describe each image in its alt text (helps Google Images and accessibility).'],
        'low_content_rate' => ['Little text compared with code', 'notice', 'Add useful copy, or trim heavy scripts and markup.'],
        'low_character_count' => ['Very little text', 'notice', 'Thin pages rarely rank: add the detail shoppers look for.'],
        'high_loading_time' => ['Slow to load', 'warning', 'Compress images and defer scripts; check it in PageSpeed Insights.'],
        'large_page_size' => ['Very large pages', 'notice', 'Shrink images and remove unused code.'],
        'redirect_chain' => ['Redirect chains', 'warning', 'Point links and redirects straight at the final page.'],
        'redirect_loop' => ['Redirect loops', 'error', 'Fix redirects that send the page back to itself.'],
        'canonical_to_broken' => ['Canonical points to a broken page', 'error', 'Point the canonical tag at the live page.'],
        'canonical_to_redirect' => ['Canonical points to a redirect', 'warning', 'Point the canonical tag at the final address.'],
        'https_to_http_links' => ['Links from secure to non-secure pages', 'notice', 'Change http:// links to https://.'],
        'non_indexable' => ['Pages Google is told not to index', 'notice', 'Check each is meant to be hidden (noindex, robots.txt or canonical elsewhere).'],
        'is_orphan_page' => ['Pages nothing links to', 'notice', 'Link to them from a related page or a category.'],
        'has_render_blocking_resources' => ['Scripts or styles that block the page from showing', 'notice', 'Load them later (defer or async).'],
        'broken_resources' => ['Broken images, scripts or styles', 'warning', 'Fix or remove files that no longer load.'],
    ];

    public static function latest(Brand $brand): ?SeoAudit
    {
        return SeoAudit::where('brand_id', $brand->id)->latest('id')->first();
    }

    public static function lastFinished(Brand $brand): ?SeoAudit
    {
        return SeoAudit::where('brand_id', $brand->id)->where('status', 'done')->latest('id')->first();
    }

    /** Why DataForSEO couldn't crawl a site, from its extended_crawl_status. */
    public const BLOCKED = [
        'site_unreachable' => 'DataForSEO couldn’t reach the site. Its firewall or bot protection may be blocking crawlers, or the address didn’t answer.',
        'invalid_page_status_code' => 'The home page answered DataForSEO’s crawler with an error code, which usually means a firewall or bot protection blocked it.',
        'forbidden_robots' => 'The site’s robots.txt tells crawlers not to visit.',
        'forbidden_meta_tag' => 'The home page has a robots meta tag telling crawlers not to follow it.',
        'forbidden_http_header' => 'The site sends an X-Robots-Tag header telling crawlers not to follow it.',
        'too_many_redirects' => 'The home page redirects too many times (often a loop between www and non-www, or http and https).',
    ];

    /** What a regular desktop browser sends, for a second try when the site turns DataForSEO's own crawler away. */
    private const BROWSER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    /** Start a crawl unless one is already running. $asBrowser crawls as a regular browser would identify itself. */
    public static function start(Brand $brand, ?int $userId, bool $asBrowser = false): SeoAudit
    {
        $running = SeoAudit::where('brand_id', $brand->id)->where('status', 'crawling')->latest('id')->first();
        if ($running) {
            return $running;
        }
        $domain = SeoSuite::domain($brand) ?? throw new RuntimeException('No domain is set for '.$brand->name.'.');
        $pages = (int) config('dataforseo.audit_pages');
        $tasks = DataForSeo::postTasks('/v3/on_page/task_post', [[
            'target' => $domain,
            // Start where the site really lives (usually www.), not the bare domain, which may not answer.
            'start_url' => self::startUrl($brand, $domain),
            'max_crawl_pages' => $pages, 'load_resources' => false, 'enable_javascript' => false,
            'respect_sitemap' => true, 'tag' => $brand->slug,
        ] + ($asBrowser ? ['custom_user_agent' => self::BROWSER_AGENT] : [])], $userId);
        $t = $tasks[0] ?? [];
        if ((int) ($t['status_code'] ?? 0) !== 20100 || ! isset($t['id'])) {
            DataForSeo::task(['tasks' => [$t]]);
            throw new RuntimeException('DataForSEO didn’t start the crawl.');
        }

        return SeoAudit::create(['brand_id' => $brand->id, 'task_id' => (string) $t['id'], 'status' => 'crawling', 'max_pages' => $pages,
            'started_by' => $userId, 'summary' => ['browser' => $asBrowser]]);
    }

    /** The brand's own web address when it's on the measured domain (https://www.39dollarglasses.com/), else https://domain/. */
    public static function startUrl(Brand $brand, string $domain): string
    {
        $host = strtolower((string) parse_url((string) $brand->site, PHP_URL_HOST));
        $same = $host === $domain || $host === 'www.'.$domain;

        return 'https://'.($same ? $host : $domain).'/';
    }

    /** Check running crawls (free) and start the monthly one for brands that are due. */
    public static function refreshIfStale(): void
    {
        if (! DataForSeo::configured() || ! Cache::add('dfs:audit:busy', 1, 300)) {
            return;
        }
        dispatch(function () {
            try {
                self::run();
            } finally {
                Cache::forget('dfs:audit:busy');
            }
        })->afterResponse();
    }

    public static function run(): void
    {
        foreach (SeoAudit::where('status', 'crawling')->get() as $audit) {
            try {
                self::poll($audit);
            } catch (Throwable $e) {
                Log::warning('DataForSEO audit check failed', ['audit' => $audit->id, 'error' => $e->getMessage()]);
                // Never leave an audit "crawling" for good: after a day it's marked failed, with the last error.
                if ($audit->created_at->lt(now()->subDay())) {
                    $audit->update(['status' => 'failed', 'error' => mb_substr('The crawl couldn’t be read back: '.$e->getMessage(), 0, 500), 'finished_at' => now()]);
                }
            }
        }
        foreach (SeoSuite::brands() as $brand) {
            $last = self::latest($brand);
            if (! $last || ($last->status !== 'crawling' && $last->created_at->lt(now()->subDays((int) config('dataforseo.audit_days'))))) {
                try {
                    self::start($brand, null);
                } catch (Throwable $e) {
                    Log::warning('DataForSEO audit start failed', ['brand' => $brand->slug, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    public static function poll(SeoAudit $audit): void
    {
        $task = DataForSeo::get('/v3/on_page/summary/'.rawurlencode((string) $audit->task_id));
        $code = (int) ($task['status_code'] ?? 0);
        $r = $task['result'][0] ?? null;
        if ($code !== 20000 || ! is_array($r)) {
            // Queued or handed to a crawler: wait. Anything else is DataForSEO saying the task failed: say why.
            $waiting = in_array($code, [20100, 40601, 40602], true) || $code === 0;
            if (! $waiting) {
                $audit->update(['status' => 'failed', 'finished_at' => now(),
                    'error' => mb_substr('DataForSEO couldn’t crawl the site: '.((string) ($task['status_message'] ?? 'no reason given')).' ('.$code.').', 0, 500)]);
            } elseif ($audit->created_at->lt(now()->subDay())) {
                $audit->update(['status' => 'failed', 'error' => 'The crawl didn’t finish within a day.', 'finished_at' => now()]);
            }

            return;
        }
        $audit->update(['pages_crawled' => (int) ($r['crawl_status']['pages_crawled'] ?? 0)]);
        if (($r['crawl_progress'] ?? '') !== 'finished') {
            if ($audit->created_at->lt(now()->subDay())) {
                $audit->update(['status' => 'failed', 'error' => 'The crawl didn’t finish within a day.', 'finished_at' => now()]);
            }

            return;
        }

        // A site that turned the crawler away "finishes" with next to nothing crawled. Say why, and try once more as a browser.
        $crawled = (int) ($r['crawl_status']['pages_crawled'] ?? 0);
        $why = (string) ($r['domain_info']['extended_crawl_status'] ?? 'no_errors');
        if ($crawled <= 1 && ($why !== 'no_errors' || $crawled === 0)) {
            $asBrowser = (bool) ($audit->summary['browser'] ?? false);
            $reason = self::BLOCKED[$why] ?? 'DataForSEO crawled '.$crawled.' '.($crawled === 1 ? 'page' : 'pages').' and stopped ('.str_replace('_', ' ', $why).').';
            $audit->update(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($reason.($asBrowser
                ? ' A second try as a regular browser was blocked too: ask the developer to allow DataForSEO’s crawler (user agent “RSiteAuditor”) in the firewall or bot protection.'
                : ' Trying again as a regular browser.'), 0, 500)]);
            if (! $asBrowser) {
                self::start($audit->brand, $audit->started_by, true);
            }

            return;
        }

        $m = $r['page_metrics'] ?? [];
        $counts = [];
        foreach (self::ISSUES as $key => $_) {
            $n = $m[$key] ?? $m['checks'][$key] ?? null;
            if (is_numeric($n) && $n > 0) {
                $counts[$key] = (int) $n;
            }
        }
        // The page list is a bonus: if it can't be read, the audit still finishes with its score and issue counts.
        try {
            $pages = DataForSeo::post('/v3/on_page/pages', ['id' => $audit->task_id, 'limit' => 100, 'order_by' => ['onpage_score,asc'],
                'filters' => [['resource_type', '=', 'html']]]);
        } catch (RuntimeException $e) {
            Log::warning('DataForSEO audit pages failed', ['audit' => $audit->id, 'error' => $e->getMessage()]);
            $pages = [];
        }

        $audit->update([
            'status' => 'done',
            'score' => isset($m['onpage_score']) ? round((float) $m['onpage_score'], 1) : null,
            'summary' => ['issues' => $counts, 'pages' => (int) ($r['domain_info']['total_pages'] ?? $audit->pages_crawled), 'stop' => $r['crawl_stop_reason'] ?? null,
                'browser' => (bool) ($audit->summary['browser'] ?? false)],
            'pages' => array_values(array_filter(array_map(fn ($p) => isset($p['url']) ? [
                'url' => mb_substr((string) $p['url'], 0, 500),
                'status' => (int) ($p['status_code'] ?? 0),
                'score' => isset($p['onpage_score']) ? round((float) $p['onpage_score'], 1) : null,
                'title' => mb_substr((string) ($p['meta']['title'] ?? ''), 0, 200),
                'issues' => array_values(array_filter(array_keys(self::ISSUES), fn ($k) => ($p['checks'][$k] ?? false) === true || ($p[$k] ?? false) === true)),
            ] : null, $pages['items'] ?? []))),
            'finished_at' => now(),
        ]);
    }
}
