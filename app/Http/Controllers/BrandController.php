<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Support\Launchpad;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BrandController extends Controller
{
    /** Brand hub: everything for one brand on one page. Open to everyone signed in. */
    public function show(Request $request, Brand $brand): View
    {
        Launchpad::recordOpen($request->user(), $brand);
        $favKeys = Launchpad::favoriteKeys($request->user());
        $myRequests = \App\Models\AccessRequest::latestFor($request->user());
        $brokenLinks = $request->user()->can('admin') ? \App\Models\LinkCheck::brokenKeys() : collect();
        $source = $brand->accountSource();
        $source->load(['accounts' => fn ($q) => $q->orderBy('platform')]);
        $tools = \App\Models\Tool::with('brands')->orderBy('sort')->orderBy('name')->get()
            ->filter(fn ($t) => $t->all_brands || $t->brands->contains('id', $brand->id))->values();
        $routines = \App\Models\Routine::with(['brands', 'latestCompletion.user', 'assignee'])->orderBy('sort')->get()
            ->filter(fn ($r) => $r->all_brands || $r->brands->contains('id', $brand->id))->values();
        $platforms = config('omniven.platforms');
        $groups = collect(config('omniven.platform_groups'))
            ->map(fn ($label, $key) => ['label' => $label, 'accounts' => $source->accounts->filter(fn ($a) => $a->group() === $key)->values()])
            ->filter(fn ($g) => $g['accounts']->isNotEmpty());
        // A brand that uses another's accounts doesn't nag for its own; gaps show on that brand's hub.
        $missing = $source->is($brand)
            ? collect($platforms)->filter(fn ($p, $k) => $p['grid'] && ! $source->accounts->contains('platform', $k))
            : collect();

        return view('brands.show', compact('brand', 'source', 'tools', 'routines', 'groups', 'missing', 'favKeys', 'myRequests', 'brokenLinks'));
    }

    public function create(): View
    {
        return view('brands.form', ['brand' => new Brand(['sort' => Brand::max('sort') + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $base = Str::slug($data['name'], '') ?: 'brand';
        $slug = $base;
        for ($i = 2; Brand::where('slug', $slug)->exists(); $i++) {
            $slug = $base.$i;
        }
        $brand = Brand::create($data + ['slug' => $slug]);
        Activity::log('brand.created', "Added brand “{$brand->name}”", $brand);

        return redirect()->route('dashboard', ['#brands'])->with('status', "Added {$brand->name}.");
    }

    public function edit(Brand $brand): View
    {
        return view('brands.form', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validated($request));
        Activity::log('brand.updated', "Edited brand “{$brand->name}”", $brand);

        return redirect()->route('dashboard')->with('status', "Saved {$brand->name}.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Activity::log('brand.deleted', "Removed brand “{$brand->name}”", $brand);
        $brand->delete();

        return redirect()->route('dashboard')->with('status', "Removed {$brand->name}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'site' => ['nullable', 'url:http,https', 'max:1024'],
            'focus' => ['nullable', 'string', 'max:1000'],
            'channels' => ['nullable', 'string', 'max:1000'],
            'socials_text' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'accounts_from_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        // One level only: the chosen brand must use its own accounts, and can't be this brand.
        if (filled($data['accounts_from_id'] ?? null)) {
            $from = Brand::find($data['accounts_from_id']);
            $self = $request->route('brand');
            if (($self instanceof Brand && $from->is($self)) || $from->accounts_from_id) {
                throw ValidationException::withMessages(['accounts_from_id' => 'Pick a brand that has its own accounts.']);
            }
            if ($self instanceof Brand && Brand::where('accounts_from_id', $self->id)->exists()) {
                throw ValidationException::withMessages(['accounts_from_id' => "Other brands use {$self->name}'s accounts, so it needs to keep its own."]);
            }
        } else {
            $data['accounts_from_id'] = null;
        }

        $socials = [];
        foreach (preg_split('/\R/', (string) ($data['socials_text'] ?? '')) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL) || ($url !== '' && ! preg_match('#^https?://#i', $url))) {
                throw ValidationException::withMessages(['socials_text' => "\"{$url}\" isn't a valid https:// link."]);
            }
            $socials[] = ['label' => $label, 'url' => $url];
        }
        unset($data['socials_text']);
        $data['socials'] = $socials;
        $data['sort'] ??= 100;

        return $data;
    }
}
