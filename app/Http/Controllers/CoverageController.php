<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Brand;
use App\Models\LinkCheck;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Coverage: every brand against every platform, with the gaps that matter (admins mark the ones a brand
 * doesn't need) and the accounts that need attention (no direct link, access not verified, broken link).
 */
class CoverageController extends Controller
{
    public function index(Request $request): View
    {
        $platforms = collect(config('omniven.platforms'))->except('other');
        $brands = Brand::orderBy('sort')->get();
        $accounts = Account::with('brand')->get()->groupBy('brand_id')->map(fn ($as) => $as->groupBy('platform')->map->first());
        $skips = DB::table('brand_platform_skips')->get()->groupBy('brand_id')->map(fn ($r) => $r->pluck('platform')->flip());
        $broken = LinkCheck::brokenKeys();

        $rows = $brands->map(function ($b) use ($platforms, $accounts, $skips, $brands) {
            $src = $b->accounts_from_id ? $brands->firstWhere('id', $b->accounts_from_id) : null;
            // Accounts saved for "All brands" cover every brand; a brand's own account wins.
            // (Plain arrays: merging Eloquent collections would re-key them by id.)
            $mine = collect(array_replace(($accounts[''] ?? collect())->all(), ($accounts[$src?->id ?? $b->id] ?? collect())->all()));
            $skip = $skips[$b->id] ?? collect();
            $expected = $platforms->keys()->reject(fn ($k) => isset($skip[$k]));

            return ['brand' => $b, 'source' => $src, 'accounts' => $mine, 'skip' => $skip,
                'have' => $expected->filter(fn ($k) => isset($mine[$k]))->count(), 'expected' => $expected->count()];
        });
        $own = $rows->whereNull('source');
        $gaps = $own->sum(fn ($r) => $r['expected'] - $r['have']);

        $attention = Account::with('brand')->get()->filter(fn ($a) => isset($platforms[$a->platform]))->map(fn ($a) => [
            'account' => $a,
            'issues' => array_values(array_filter([
                $broken->has('account:'.$a->id) ? 'Link is broken' : null,
                ! $a->hasDirectLink() ? 'No direct link' : null,
                $a->needsVerify() ? ($a->verified_on ? 'Access last checked '.$a->verified_on->format('M j, Y') : 'Access never checked') : null,
            ])),
        ])->filter(fn ($x) => $x['issues'])->sortBy(fn ($x) => [in_array('Link is broken', $x['issues'], true) ? 0 : 1, $x['account']->brand?->sort ?? 999])->values();

        return view('coverage.index', [
            'groups' => $platforms->groupBy('group', true),
            'groupLabels' => config('omniven.platform_groups'),
            'rows' => $rows,
            'score' => $own->sum('expected') ? (int) round($own->sum('have') / $own->sum('expected') * 100) : 0,
            'gaps' => $gaps,
            'attention' => $attention,
            'broken' => $broken,
        ]);
    }

    /** Admins: mark a platform as not needed for a brand, or needed again. */
    public function skip(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'platform' => ['required', Rule::in(array_keys(config('omniven.platforms')))],
        ]);
        $brand = Brand::find($data['brand_id']);
        $label = config('omniven.platforms.'.$data['platform'].'.label');
        $q = DB::table('brand_platform_skips')->where('brand_id', $brand->id)->where('platform', $data['platform']);
        if ($q->exists()) {
            $q->delete();
            $msg = "{$label} counts for {$brand->name} again.";
        } else {
            DB::table('brand_platform_skips')->insert(['brand_id' => $brand->id, 'platform' => $data['platform'], 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $msg = "{$brand->name} doesn’t need {$label}.";
        }
        Activity::log('coverage.skip', $msg);

        return redirect()->to(route('coverage').'#g-'.config('omniven.platforms.'.$data['platform'].'.group'))->with('status', $msg);
    }
}
