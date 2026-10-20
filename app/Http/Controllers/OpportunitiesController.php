<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Opportunity;
use App\Models\OpportunityTerm;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity;
use App\Support\Opportunities\OpportunityFinder;
use App\Support\Opportunities\Tango;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ocusafe opportunities, part one: government bids and RFPs for safety glasses, found daily through Tango. Anyone
 * can work the list (pursue, pass, take it on, make a task); admins tune the search phrases.
 */
class OpportunitiesController extends Controller
{
    public const VIEWS = [
        'good' => 'Good matches',
        'all' => 'All open',
        'mine' => 'Mine',
        'pursuing' => 'Pursuing',
        'passed' => 'Passed',
        'closed' => 'Closed',
    ];

    public function index(Request $request): View
    {
        OpportunityFinder::refreshIfStale();
        $me = $request->user();
        $view = array_key_exists((string) $request->query('view'), self::VIEWS) ? (string) $request->query('view') : 'good';
        $source = array_key_exists((string) $request->query('source'), Opportunity::SOURCES) ? (string) $request->query('source') : null;
        $q = mb_substr(trim((string) $request->query('q')), 0, 80);
        $open = fn ($query) => $query->where('open', true)->where(fn ($w) => $w->whereNull('deadline_at')->orWhere('deadline_at', '>', now()));
        $good = (int) config('tango.good_score');

        $list = Opportunity::with(['owner', 'task'])
            ->when($source, fn ($w) => $w->where('source', $source))
            ->when($q !== '', fn ($w) => $w->whereRaw("(lower(title) like ? or lower(coalesce(agency, '')) like ? or lower(coalesce(solicitation_number, '')) like ?)", array_fill(0, 3, '%'.mb_strtolower($q).'%')))
            ->when($view === 'good', fn ($w) => $open($w)->where('score', '>=', $good)->whereNotIn('status', ['passed', 'lost']))
            ->when($view === 'all', fn ($w) => $open($w)->where('status', '!=', 'passed'))
            ->when($view === 'mine', fn ($w) => $w->where('owner_id', $me->id)->whereNotIn('status', ['passed', 'lost']))
            ->when($view === 'pursuing', fn ($w) => $w->whereIn('status', ['pursuing', 'submitted']))
            ->when($view === 'passed', fn ($w) => $w->where('status', 'passed'))
            ->when($view === 'closed', fn ($w) => $w->where(fn ($x) => $x->where('open', false)->orWhere('deadline_at', '<=', now()))->where('status', '!=', 'passed'))
            ->orderByRaw("case when status = 'new' then 0 else 1 end")
            ->orderByRaw('deadline_at is null')->orderBy('deadline_at')->orderByDesc('score')
            ->paginate(40)->withQueryString();

        $openAll = $open(Opportunity::query());

        return view('opportunities.index', [
            'configured' => Tango::configured(),
            'status' => OpportunityFinder::status(),
            'list' => $list,
            'view' => $view,
            'source' => $source,
            'q' => $q,
            'focus' => $request->integer('o') ?: null,
            'counts' => [
                'good' => (clone $openAll)->where('score', '>=', $good)->whereNotIn('status', ['passed', 'lost'])->count(),
                'new_week' => Opportunity::where('first_seen_at', '>=', now()->subWeek())->where('score', '>=', $good)->count(),
                'closing' => (clone $openAll)->where('score', '>=', $good)->whereNotIn('status', ['passed', 'lost'])->where('deadline_at', '<=', now()->addDays(7))->count(),
                'pursuing' => Opportunity::whereIn('status', ['pursuing', 'submitted'])->count(),
                'won' => Opportunity::where('status', 'won')->count(),
            ],
            'people' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // The phrase list (seeded from config the first time) is for admins to tune.
            'terms' => $request->user()->can('admin') ? tap(OpportunityTerm::orderBy('term'), fn () => OpportunityFinder::terms())->get() : collect(),
        ]);
    }

    /** Pursue, pass, mark a bid submitted, won or lost, and who owns it. */
    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Opportunity::STATUSES))],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);
        $changes = [];
        if ($request->has('status') && $data['status'] && $data['status'] !== $opportunity->status) {
            $opportunity->fill(['status' => $data['status'], 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            $changes[] = 'marked it '.strtolower(Opportunity::STATUSES[$data['status']]);
        }
        if ($request->has('owner_id') && (int) ($data['owner_id'] ?? 0) !== (int) $opportunity->owner_id) {
            $opportunity->owner_id = $data['owner_id'] ?? null;
            $changes[] = $opportunity->owner_id ? 'gave it to '.User::find($opportunity->owner_id)->name : 'cleared the owner';
        }
        if ($changes) {
            $opportunity->save();
            Activity::log('opportunity.updated', 'Bid “'.Str::limit($opportunity->title, 120).'”: '.implode(', ', $changes), $opportunity);
        }

        return back()->with('status', $changes ? 'Saved: '.implode(', ', $changes).'.' : 'Nothing changed.');
    }

    /** A task to prepare the bid, due two days before the deadline, for the owner (or whoever pressed it). */
    public function task(Request $request, Opportunity $opportunity): RedirectResponse
    {
        if ($opportunity->task && ! $opportunity->task->isDone()) {
            return back()->with('status', 'This bid already has an open task.');
        }
        $who = $opportunity->owner ?? $request->user();
        $due = $opportunity->deadline_at?->copy()->subDays(2)->startOfDay();
        $task = Task::create([
            'title' => Str::limit('Ocusafe bid: '.$opportunity->title, 190),
            'details' => implode("\n", array_filter([
                $opportunity->agency, $opportunity->solicitation_number ? 'Solicitation '.$opportunity->solicitation_number : null,
                $opportunity->deadline_at ? 'Responses due '.$opportunity->deadline_at->format('M j, Y g:i A') : null,
                $opportunity->url, 'Read the solicitation, check the specs (ANSI Z87.1, prescription, quantities), price it and submit before the deadline.',
                'In OmniVEN: '.route('opportunities', ['o' => $opportunity->id]),
            ])),
            'brand_id' => Brand::where('slug', 'ocusafe')->value('id'),
            'assignee_id' => $who->id,
            'created_by' => $request->user()->id, 'assigned_at' => now(), 'assigned_by' => $request->user()->id,
            'hot' => $opportunity->daysLeft() !== null && $opportunity->daysLeft() <= 7,
            'due_on' => $due && $due->isFuture() ? $due : $opportunity->deadline_at?->startOfDay(),
        ]);
        $opportunity->update(['task_id' => $task->id, 'owner_id' => $who->id, 'status' => $opportunity->status === 'new' ? 'pursuing' : $opportunity->status,
            'decided_by' => $request->user()->id, 'decided_at' => now()]);
        Activity::log('opportunity.tasked', 'Made a task for bid “'.Str::limit($opportunity->title, 120)."” for {$who->name}", $task);

        return back()->with('status', 'Task added for '.($who->is($request->user()) ? 'you' : $who->firstName()).'.');
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $data = $request->validate(['term' => ['required', 'string', 'min:3', 'max:120']]);
        $term = mb_strtolower(trim(preg_replace('/\s+/', ' ', $data['term'])));
        OpportunityFinder::terms();
        OpportunityTerm::firstOrCreate(['term' => $term], ['created_by' => $request->user()->id]);
        Activity::log('opportunity.term', "Added the bid search “{$term}”");

        return back()->with('status', "Added “{$term}”. It’s searched from the next daily run.");
    }

    public function destroyTerm(OpportunityTerm $term): RedirectResponse
    {
        $term->delete();
        Activity::log('opportunity.term', "Removed the bid search “{$term->term}”");

        return back()->with('status', "Removed “{$term->term}”.");
    }

    public function refresh(): RedirectResponse
    {
        if (! Cache::add('opps:refresh-now', 1, 600)) {
            return back()->with('error', 'A search ran a few minutes ago.');
        }
        OpportunityFinder::refreshIfStale(true);

        return back()->with('status', 'Searching Tango now. Reload in a minute or two.');
    }
}
