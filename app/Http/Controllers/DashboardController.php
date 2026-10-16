<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Support\Launchpad;
use App\Models\AccessRequest;
use App\Models\Account;
use App\Models\Brand;
use App\Models\Note;
use App\Models\Routine;
use App\Models\Task;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        Activity::visit($me);

        $brands = Brand::orderBy('sort')->orderBy('name')->get();
        $tools = Tool::with('brands')->orderBy('sort')->orderBy('name')->get();
        $routines = Routine::with(['brands', 'latestCompletion.user', 'assignee', 'assigner'])->orderBy('sort')->orderBy('title')->get();
        $notes = Note::with('user')->orderByDesc('pinned')->latest()->limit(12)->get();

        $categories = collect(config('omniven.categories'))
            ->merge($tools->pluck('category')->filter()->unique()->diff(config('omniven.categories'))->sort())
            ->map(fn ($c) => ['name' => $c, 'tools' => $tools->where('category', $c)->values()])
            ->filter(fn ($c) => $c['tools']->isNotEmpty())
            ->values();
        $uncategorized = $tools->whereNull('category')->values();

        // Tasks: everything open, plus the last 14 days of finished work.
        $openTasks = Task::with(['assignee', 'creator', 'assigner', 'brand'])->open()
            ->orderByDesc('hot')->orderByRaw('due_on is null')->orderBy('due_on')->latest()->get();
        $doneTasks = Task::with(['assignee', 'completer', 'assigner', 'brand'])->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(14))->latest('completed_at')->limit(200)->get();
        $taskBrands = Task::brandChoices();
        $board = $this->board($me, $openTasks, $routines);
        $hotlist = $openTasks->where('hot', true)->values();

        // Once a day, pop up the hot items assigned to the person signing in.
        $popup = collect();
        if (! $me->hotlist_popup_on?->isToday()) {
            $popup = $hotlist->where('assignee_id', $me->id)->values();
            if ($popup->isNotEmpty()) {
                $me->forceFill(['hotlist_popup_on' => today()])->save();
            }
        }

        // Brand × platform grid: [brand_id][platform] => first account.
        $accountGrid = Account::all()->groupBy('brand_id')->map(fn ($as) => $as->groupBy('platform')->map->first());
        $gridPlatforms = collect(config('omniven.platforms'))->filter(fn ($p) => $p['grid']);
        $postits = $me->postits()->with('items')->get();
        $launchpad = Launchpad::forUser($me);
        $favKeys = Launchpad::favoriteKeys($me);
        $myRequests = AccessRequest::latestFor($me);
        $openRequests = $me->can('admin') ? AccessRequest::open()->with('user')->oldest()->get() : collect();
        $brokenLinks = $me->can('admin') ? \App\Models\LinkCheck::brokenKeys() : collect();
        $calWeek = \App\Models\CalendarWeek::current();
        $calProgress = $calWeek ? ['done' => $calWeek->checks()->count(), 'total' => $calWeek->items()->count()] : null;

        // Fresh sign-in, or the logo link (/?home=1): reset to Routines, all brands.
        $goHome = $request->session()->get('omniven_home', false) || $request->boolean('home');

        $sessionEndsAt = (int) $request->session()->get('signed_in_at', now()->timestamp) + config('omniven.session_hours') * 3600;

        return view('dashboard', compact(
            'brands', 'tools', 'routines', 'notes', 'categories', 'uncategorized',
            'openTasks', 'doneTasks', 'taskBrands', 'board', 'hotlist', 'popup', 'postits', 'sessionEndsAt', 'goHome', 'accountGrid', 'gridPlatforms', 'launchpad', 'favKeys', 'myRequests', 'openRequests', 'brokenLinks', 'calWeek', 'calProgress'
        ));
    }

    /**
     * The task board: one column per marketing person (Leona, Chanley, Kaylie…), then anyone else with
     * open work, and always you. Each column holds that person's open tasks and the routines assigned to
     * them for the current day, week, month or quarter (done ones stay, crossed out, until they reset).
     */
    private function board(User $me, $openTasks, $routines)
    {
        $cadenceOrder = array_flip(array_keys(config('omniven.cadences')));
        $people = User::where('is_active', true)->whereNotNull('board_position')->orderBy('board_position')->orderBy('name')->get();
        $others = User::where('is_active', true)->whereNull('board_position')
            ->where(fn ($q) => $q->whereIn('id', $openTasks->pluck('assignee_id'))->orWhereIn('id', $routines->pluck('assignee_id')->filter())->orWhere('id', $me->id))
            ->orderBy('name')->get();

        return $people->concat($others)->map(fn (User $u) => [
            'user' => $u,
            'tasks' => $openTasks->where('assignee_id', $u->id)
                ->sortBy(fn ($t) => [$t->hot ? 0 : 1, $t->isOverdue() ? 0 : 1, $t->due_on?->timestamp ?? PHP_INT_MAX, -$t->created_at->timestamp])->values(),
            'routines' => $routines->where('assignee_id', $u->id)
                ->sortBy(fn ($r) => [$r->isDone() ? 1 : 0, $cadenceOrder[$r->cadence] ?? 9, $r->sort])->values(),
        ])->values();
    }
}
