<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\UtmLink;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Campaigns: a named push with dates that ties together its campaign links (same utm_campaign tag),
 * its tasks and launch-template runs, and their results. Running campaigns show on the Action Calendar.
 * Everyone can plan campaigns; only admins delete one.
 */
class CampaignController extends Controller
{
    public function index(): View
    {
        $today = $this->today();
        $all = Campaign::with(['brand', 'owner'])->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at'), 'links'])
            ->where('ends_on', '>=', $today->subDays(90)->format('Y-m-d'))->orderBy('starts_on')->get();
        $tags = Campaign::pluck('tag');

        return view('campaigns.index', [
            'running' => $all->filter(fn ($c) => $c->state() === 'running')->values(),
            'upcoming' => $all->filter(fn ($c) => $c->state() === 'upcoming')->values(),
            'ended' => $all->filter(fn ($c) => $c->state() === 'ended')->sortByDesc('ends_on')->values(),
            // Link tags used in the last 90 days that aren't a campaign yet.
            'untracked' => UtmLink::where('created_at', '>=', $today->subDays(90))->whereNotIn('campaign', $tags)
                ->selectRaw('campaign, count(*) as n, min(created_at) as first_at, max(brand_id) as brand_id')->groupBy('campaign')->orderByDesc('n')->limit(12)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $start = $request->query('starts_on') ?: $this->today()->format('Y-m-d');

        return view('campaigns.form', ['campaign' => new Campaign([
            'name' => (string) $request->query('name'), 'tag' => (string) $request->query('tag'),
            'brand_id' => $request->integer('brand') ?: null, 'owner_id' => $request->user()->id,
            'starts_on' => $start, 'ends_on' => $request->query('ends_on') ?: CarbonImmutable::parse($start)->addDays(13)->format('Y-m-d'),
        ])] + $this->choices());
    }

    public function store(Request $request): RedirectResponse
    {
        $c = Campaign::create($this->validated($request));
        Activity::log('campaign.added', "Planned the campaign “{$c->name}” ({$c->dateLabel()})", $c);

        return redirect()->route('campaigns.show', $c)->with('status', 'Campaign added. Add its tasks and links below.');
    }

    public function show(Campaign $campaign): View
    {
        $tasks = $campaign->tasks()->with(['assignee', 'completer'])->orderByRaw('completed_at is not null')->orderByRaw('due_on is null')->orderBy('due_on')->get();

        return view('campaigns.show', [
            'c' => $campaign->load(['brand', 'owner']),
            'open' => $tasks->whereNull('completed_at')->values(),
            'done' => $tasks->whereNotNull('completed_at')->values(),
            'results' => $tasks->filter(fn ($t) => $t->outcome || $t->outcome_url)->values(),
            'links' => $campaign->links()->with('user')->latest()->get(),
            'runs' => $campaign->runs()->with('template')->get(),
            'templates' => TaskTemplate::orderBy('sort')->orderBy('name')->get(),
            'today' => $this->today(),
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        return view('campaigns.form', ['campaign' => $campaign] + $this->choices());
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($this->validated($request, $campaign));

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Saved.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('admin');
        Activity::log('campaign.deleted', "Removed the campaign “{$campaign->name}”");
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('status', 'Campaign removed. Its tasks and links stay.');
    }

    private function validated(Request $request, ?Campaign $c = null): array
    {
        $request->merge(['tag' => Campaign::tagFor((string) ($request->input('tag') ?: $request->input('name')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tag' => ['required', 'string', 'max:150', Rule::unique('campaigns', 'tag')->ignore($c?->id)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'goal' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], ['tag.unique' => 'Another campaign already uses that link tag.', 'tag.required' => 'Give the campaign a name with letters or numbers.']);

        return $data;
    }

    private function choices(): array
    {
        return ['brands' => Brand::orderBy('sort')->get(), 'people' => User::where('is_active', true)->orderBy('name')->get()];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }
}
