<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SeoKeyword;
use App\Support\Activity;
use App\Support\Seo\DataForSeo;
use App\Support\Seo\RankTracker;
use App\Support\Seo\SeoSuite;
use App\Support\Seo\SiteAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The SEO suite: an overview per brand, keyword research, rank tracking, competitors and keyword gaps,
 * backlinks, site audits and AI visibility, all from DataForSEO. Until DataForSEO is set up only admins
 * can open it, and they see the setup steps.
 */
class SeoSuiteController extends Controller
{
    public function overview(Request $request): View
    {
        $brand = $this->brand($request);
        if ($brand) {
            SeoSuite::refreshIfStale('overview', $brand);
            SeoSuite::refreshIfStale('competitors', $brand);
            SeoSuite::refreshIfStale('backlinks', $brand);
            SeoSuite::refreshIfStale('ai', $brand);
            RankTracker::refreshIfStale();
            SiteAudit::refreshIfStale();
        }
        $tracked = $brand ? SeoKeyword::where('brand_id', $brand->id)->get() : collect();

        return $this->page('overview', $request, $brand, [
            'report' => $brand ? SeoSuite::report('overview', $brand) : null,
            'tracked' => $tracked,
            'audit' => $brand ? SiteAudit::lastFinished($brand) : null,
            'backlinks' => $brand && DataForSeo::backlinks() ? SeoSuite::report('backlinks', $brand)['data'] : null,
            'ai' => $brand ? SeoSuite::report('ai', $brand)['data'] : null,
        ]);
    }

    public function keywords(Request $request): View
    {
        $brand = $this->brand($request);
        $q = mb_substr(trim((string) $request->query('q')), 0, 80);
        $mode = array_key_exists((string) $request->query('mode'), SeoSuite::RESEARCH_MODES) ? (string) $request->query('mode') : 'ideas';
        $rows = null;
        $error = null;
        if ($q !== '' && DataForSeo::configured()) {
            $key = 'dfs-lookup:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($key, (int) config('dataforseo.lookups_per_hour'))) {
                $error = 'That’s a lot of lookups this hour. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.';
            } else {
                RateLimiter::hit($key, 3600);
                try {
                    $rows = SeoSuite::research($q, $mode, $request->user()->id);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->page('keywords', $request, $brand, [
            'q' => $q, 'mode' => $mode, 'rows' => $rows, 'error' => $error,
            'tracked' => $brand ? SeoKeyword::where('brand_id', $brand->id)->pluck('keyword')->flip() : collect(),
        ]);
    }

    public function rankings(Request $request): View
    {
        $brand = $this->brand($request);
        RankTracker::refreshIfStale();
        $keywords = $brand ? SeoKeyword::where('brand_id', $brand->id)->with(['checks' => fn ($q) => $q->where('checked_on', '>=', now()->subMonths(6))->orderBy('checked_on')])->get() : collect();
        $sort = in_array($request->query('sort'), ['change', 'volume', 'keyword'], true) ? $request->query('sort') : 'position';
        $keywords = match ($sort) {
            'change' => $keywords->sortByDesc(fn ($k) => abs($k->change() ?? 0)),
            'volume' => $keywords->sortByDesc('search_volume'),
            'keyword' => $keywords->sortBy('keyword'),
            default => $keywords->sortBy(fn ($k) => $k->position ?? 999),
        };

        return $this->page('rankings', $request, $brand, [
            'keywords' => $keywords->values(),
            'sort' => $sort,
            'pending' => $keywords->whereNotNull('task_id')->count(),
            'error' => RankTracker::error(),
        ]);
    }

    public function storeKeywords(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand' => ['required', Rule::in(SeoSuite::brands()->keys()->all())],
            'keywords' => ['required', 'string', 'max:10000'],
        ]);
        $brand = SeoSuite::brands()->get($data['brand']);
        $added = RankTracker::add($brand, preg_split('/[\r\n,]+/', $data['keywords']), $request->user()->id);
        if ($added) {
            Activity::log('seo.tracked', "Started tracking $added Google ".($added === 1 ? 'search' : 'searches')." for {$brand->name}");
            Cache::forget('dfs:rank:busy');
            RankTracker::refreshIfStale();
        }
        $full = SeoKeyword::where('brand_id', $brand->id)->count() >= (int) config('dataforseo.max_keywords');

        return back()->with('status', $added ? "Tracking $added new ".($added === 1 ? 'search' : 'searches').'. Positions show up in a few minutes.'
            : ($full ? 'This brand already tracks the most searches allowed ('.config('dataforseo.max_keywords').'). Remove some first.' : 'Those searches are already tracked.'));
    }

    public function destroyKeyword(SeoKeyword $keyword): RedirectResponse
    {
        $keyword->delete();
        Activity::log('seo.untracked', "Stopped tracking “{$keyword->keyword}” for {$keyword->brand->name}");

        return back()->with('status', 'Stopped tracking “'.$keyword->keyword.'”.');
    }

    public function checkNow(Request $request): RedirectResponse
    {
        $brand = SeoSuite::brands()->get((string) $request->input('brand')) ?? abort(404);
        if (! Cache::add("dfs:rank:now:{$brand->slug}", 1, 3600)) {
            return back()->with('error', 'Positions for '.$brand->name.' were checked less than an hour ago.');
        }
        try {
            $n = RankTracker::queue(SeoKeyword::where('brand_id', $brand->id)->whereNull('task_id')->with('brand')->get(), $request->user()->id);
        } catch (RuntimeException $e) {
            Cache::forget("dfs:rank:now:{$brand->slug}");

            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $n ? "Checking $n ".($n === 1 ? 'search' : 'searches').' now. Refresh in a few minutes.' : 'Every search is already being checked.');
    }

    public function competitors(Request $request): View
    {
        $brand = $this->brand($request);
        if ($brand) {
            SeoSuite::refreshIfStale('competitors', $brand);
        }
        $vs = SeoSuite::cleanDomain((string) $request->query('vs'));
        $gap = null;
        $error = null;
        if ($brand && $vs && DataForSeo::configured()) {
            $key = 'dfs-lookup:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($key, (int) config('dataforseo.lookups_per_hour'))) {
                $error = 'That’s a lot of lookups this hour. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.';
            } else {
                RateLimiter::hit($key, 3600);
                try {
                    $gap = SeoSuite::gap($brand, $vs, $request->user()->id);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->page('competitors', $request, $brand, [
            'report' => $brand ? SeoSuite::report('competitors', $brand) : null,
            'vs' => $vs, 'gap' => $gap, 'error' => $error,
            'tracked' => $brand ? SeoKeyword::where('brand_id', $brand->id)->pluck('keyword')->flip() : collect(),
        ]);
    }

    public function backlinks(Request $request): View
    {
        $brand = $this->brand($request);
        if ($brand) {
            SeoSuite::refreshIfStale('backlinks', $brand);
        }

        return $this->page('backlinks', $request, $brand, ['report' => $brand ? SeoSuite::report('backlinks', $brand) : null]);
    }

    public function audit(Request $request): View
    {
        $brand = $this->brand($request);
        SiteAudit::refreshIfStale();
        $latest = $brand ? SiteAudit::latest($brand) : null;

        return $this->page('audit', $request, $brand, [
            'latest' => $latest,
            'audit' => $brand ? SiteAudit::lastFinished($brand) : null,
            'issue' => array_key_exists((string) $request->query('issue'), SiteAudit::ISSUES) ? (string) $request->query('issue') : null,
        ]);
    }

    public function startAudit(Request $request): RedirectResponse
    {
        $brand = SeoSuite::brands()->get((string) $request->input('brand')) ?? abort(404);
        if (! Cache::add("dfs:audit:now:{$brand->slug}", 1, 3600)) {
            return back()->with('error', 'An audit of '.$brand->name.' was started less than an hour ago.');
        }
        try {
            SiteAudit::start($brand, $request->user()->id);
        } catch (RuntimeException $e) {
            Cache::forget("dfs:audit:now:{$brand->slug}");

            return back()->with('error', $e->getMessage());
        }
        Activity::log('seo.audit', "Started a site audit of {$brand->name}");

        return back()->with('status', 'The crawl has started. It usually takes 5–20 minutes; this page fills in when it’s done.');
    }

    public function ai(Request $request): View
    {
        $brand = $this->brand($request);
        if ($brand) {
            SeoSuite::refreshIfStale('competitors', $brand);
            SeoSuite::refreshIfStale('ai', $brand);
        }

        return $this->page('ai', $request, $brand, ['report' => $brand ? SeoSuite::report('ai', $brand) : null]);
    }

    /** Admins can refresh a report now instead of waiting for its schedule. */
    public function refresh(Request $request): RedirectResponse
    {
        $data = $request->validate(['brand' => ['required', Rule::in(SeoSuite::brands()->keys()->all())], 'kind' => ['required', Rule::in(SeoSuite::KINDS)]]);
        $brand = SeoSuite::brands()->get($data['brand']);
        if (! Cache::add("dfs:{$data['kind']}:{$brand->slug}:now", 1, 600)) {
            return back()->with('error', 'That was refreshed a few minutes ago.');
        }
        Cache::forget("dfs:{$data['kind']}:{$brand->slug}:failed");
        SeoSuite::refreshIfStale($data['kind'], $brand, true);

        return back()->with('status', 'Refreshing from DataForSEO. Reload in a minute.');
    }

    private function brand(Request $request): ?Brand
    {
        $brands = SeoSuite::brands();

        return $brands->get((string) $request->query('brand')) ?? $brands->first();
    }

    private function page(string $page, Request $request, ?Brand $brand, array $data): View
    {
        return view("seo-suite.$page", $data + [
            'page' => $page,
            'configured' => DataForSeo::configured(),
            'brands' => SeoSuite::brands(),
            'brand' => $brand,
            'domain' => $brand ? SeoSuite::domain($brand) : null,
            'spend' => $request->user()->can('admin') && DataForSeo::configured() ? SeoSuite::spend() : null,
        ]);
    }
}
