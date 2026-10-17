<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\UtmLink;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Campaign links: tag a destination with UTM parameters from a preset, so every team member names
 * sources, mediums and campaigns the same way and the Sales page can tell channels apart.
 */
class LinkController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::orderBy('sort')->orderBy('name')->get();
        $q = trim((string) $request->query('q'));
        $brand = $brands->firstWhere('slug', $request->query('brand'));

        $links = UtmLink::with(['user', 'brand'])
            ->when($brand, fn ($w) => $w->where('brand_id', $brand->id))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $w->where(fn ($x) => $x->whereRaw('lower(campaign) like ?', [$like])->orWhereRaw('lower(source) like ?', [$like])
                    ->orWhereRaw('lower(content) like ?', [$like])->orWhereRaw('lower(base_url) like ?', [$like]));
            })
            ->latest('updated_at')->paginate(50)->withQueryString();

        $copy = $request->integer('copy') ? UtmLink::find($request->integer('copy')) : null;

        return view('links.index', [
            'brands' => $brands,
            'brand' => $brand,
            'q' => $q,
            'links' => $links,
            'made' => session('made') ? UtmLink::find(session('made')) : null,
            // "Make another like this" fills the form from an earlier link.
            'from' => $copy,
            'presets' => config('utm.presets'),
            'campaigns' => UtmLink::query()->select('campaign')->groupBy('campaign')->orderByRaw('max(updated_at) desc')->limit(60)->pluck('campaign'),
            'sources' => UtmLink::query()->where('preset', 'partner')->select('source')->groupBy('source')->orderBy('source')->limit(60)->pluck('source'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $presets = UtmLink::presets();
        $data = $request->validate([
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'base_url' => ['required', 'string', 'max:1024', 'url:http,https'],
            'preset' => ['required', Rule::in(array_keys($presets))],
            'source' => ['nullable', 'string', 'max:100'],
            'medium' => ['nullable', 'string', 'max:100'],
            'campaign' => ['required', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:150'],
            'term' => ['nullable', 'string', 'max:150'],
        ], [
            'base_url.url' => 'Enter the full page address, starting with https://',
            'campaign.required' => 'Name the campaign, e.g. fall-sale-2026.',
        ]);

        [, $presetSource, $presetMedium] = $presets[$data['preset']];
        $utm = [
            'source' => UtmLink::clean($presetSource ?? $data['source'] ?? ''),
            'medium' => UtmLink::clean($presetMedium ?? $data['medium'] ?? ''),
            'campaign' => UtmLink::clean($data['campaign']),
            'content' => UtmLink::clean($data['content'] ?? ''),
            'term' => UtmLink::clean($data['term'] ?? ''),
        ];
        $errors = [];
        foreach (['source' => 'Enter where the link will be posted (the source), e.g. the partner’s site name.', 'medium' => 'Enter the medium, e.g. referral.', 'campaign' => 'Use letters or numbers in the campaign name.'] as $k => $msg) {
            if ($utm[$k] === '') {
                $errors[$k] = $msg;
            }
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        $url = UtmLink::tag(trim($data['base_url']), $utm);
        $link = UtmLink::where('url', $url)->first();
        if ($link) {
            $link->touch();
        } else {
            $link = UtmLink::create(['user_id' => $request->user()->id, 'brand_id' => $data['brand_id'] ?? null, 'preset' => $data['preset'],
                'base_url' => trim($data['base_url']), 'url' => $url] + array_map(fn ($v) => $v === '' ? null : $v, $utm));
            Activity::log('link.created', 'Made a campaign link: '.$utm['campaign'].' ('.$utm['source'].' / '.$utm['medium'].')', $link);
        }

        // Keep the form filled in: the next link is usually the same campaign for another post or channel.
        return redirect()->route('links.index')->with('made', $link->id)->withInput($request->except('_token'));
    }

    public function destroy(Request $request, UtmLink $link): RedirectResponse
    {
        abort_unless($request->user()->is_admin || $link->user_id === $request->user()->id, 403);
        $link->delete();

        return back()->with('status', 'Link removed from the list. Links already posted keep working.');
    }
}
