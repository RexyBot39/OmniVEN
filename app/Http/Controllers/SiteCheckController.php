<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SitePage;
use App\Support\Activity;
use App\Support\Seo\SiteCheck;
use App\Support\Seo\StructuredData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Site check: structured data on key pages and the product feeds. Everyone sees it; admins keep the page list. */
class SiteCheckController extends Controller
{
    public function index(): View
    {
        SiteCheck::refreshIfStale();
        $pages = SitePage::with('brand')->get()
            ->sortBy(fn ($p) => [['fail' => 0, 'error' => 1, 'warn' => 2, 'new' => 3, 'ok' => 4][$p->status] ?? 5, $p->url])->values();

        return view('site-check.index', [
            'pages' => $pages,
            'counts' => $pages->countBy('status'),
            'feeds' => SiteCheck::feedReports(),
            'last' => SiteCheck::last(),
            'brands' => Brand::orderBy('sort')->get(),
            'kinds' => StructuredData::KINDS,
            'running' => Cache::has('site-check:running'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:1024'],
            'kind' => ['required', Rule::in(array_keys(StructuredData::KINDS))],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
        ]);
        if (SitePage::where('url', $data['url'])->exists()) {
            return back()->withErrors(['url' => 'That page is already on the list.'])->withInput();
        }
        $page = SitePage::create($data + ['user_id' => $request->user()->id]);
        SiteCheck::pages(collect([$page]));
        Activity::log('site.page_added', 'Added '.$page->path().' to the Site check');

        return back()->with('status', 'Added and checked.');
    }

    /** Add each brand's home page (from Brands) that isn't on the list yet. */
    public function addHomes(Request $request): RedirectResponse
    {
        $added = collect();
        foreach (Brand::whereNotNull('site')->get() as $b) {
            if (preg_match('#^https?://#i', $b->site) && ! SitePage::where('url', $b->site)->exists()) {
                $added->push(SitePage::create(['brand_id' => $b->id, 'url' => $b->site, 'kind' => 'home', 'user_id' => $request->user()->id]));
            }
        }
        SiteCheck::pages($added);

        return back()->with('status', $added->count().' home '.str('page')->plural($added->count()).' added and checked.');
    }

    public function destroy(SitePage $page): RedirectResponse
    {
        Activity::log('site.page_removed', 'Removed '.$page->path().' from the Site check');
        $page->delete();

        return back()->with('status', 'Removed.');
    }

    public function run(): RedirectResponse
    {
        Cache::forget('site-check:last');
        dispatch(fn () => SiteCheck::run())->afterResponse();

        return back()->with('status', 'Checking now. Pages take a few seconds, feeds up to two minutes; refresh to see the results.');
    }
}
