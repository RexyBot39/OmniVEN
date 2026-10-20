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
    /** The most finished tasks one view of the Completed list shows. */
    private const DONE_LIMIT = 500;

    public function index(Request $request): View
    {
        $me = $request->user();
        Activity::visit($me);

        $brands = Brand::orderBy('sort')->orderBy('name')->get();
        $tools = Tool::with('brands')->orderBy('sort')->orderBy('name')->get();
        $periodKeys = collect(array_keys(config('omniven.cadences')))->map(fn ($c) => Routine::periodKey($c))->all();
        $routines = Routine::with(['brands', 'latestCompletion.user', 'assignee', 'assigner', 'brandChecks' => fn ($q) => $q->whereIn('period_key', $periodKeys)->with('user')])->orderBy('sort')->orderBy('title')->get();
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
        $doneRange = $this->doneRange($request);
        $doneTasks = Task::with(['assignee', 'completer', 'assigner', 'brand'])->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$doneRange['from']->copy(), $doneRange['to']->copy()])
            ->latest('completed_at')->limit(self::DONE_LIMIT + 1)->get();
        $doneRange['more'] = $doneTasks->count() > self::DONE_LIMIT;
        $doneRange += $this->donePresets();
        $doneTasks = $doneTasks->take(self::DONE_LIMIT);
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
        $pulse = \App\Support\PulseToPost::card();
        // Sales isn't shown here, but home visits by revenue viewers keep the GA4 numbers fresh for the Sales page.
        if (\App\Support\Revenue\Revenue::configured() && \App\Support\Revenue\Revenue::canSee($me)) {
            \App\Support\Revenue\Revenue::refreshIfStale();
        }
        $calProgress = $calWeek ? ['done' => $calWeek->checks()->count(), 'total' => $calWeek->items()->count()] : null;

        // Fresh sign-in, or the logo link (/?home=1): reset to Routines, all brands.
        $goHome = $request->session()->get('omniven_home', false) || $request->boolean('home');

        $sessionEndsAt = (int) $request->session()->get('signed_in_at', now()->timestamp) + config('omniven.session_hours') * 3600;

        return view('dashboard', compact(
            'brands', 'tools', 'routines', 'notes', 'categories', 'uncategorized',
            'openTasks', 'doneTasks', 'doneRange', 'taskBrands', 'board', 'hotlist', 'popup', 'postits', 'sessionEndsAt', 'goHome', 'accountGrid', 'gridPlatforms', 'launchpad', 'favKeys', 'myRequests', 'openRequests', 'brokenLinks', 'calWeek', 'calProgress', 'pulse'
        ));
    }

    /**
     * Which finished tasks the Completed list shows: the last 14 days, or a range picked with
     * ?done_from=YYYY-MM-DD&done_to=YYYY-MM-DD (either end optional, reversed ends swapped, never past today).
     */
    private function doneRange(Request $request): array
    {
        $tz = config('app.timezone');
        $parse = function (?string $v) use ($tz) {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                $d = \Carbon\Carbon::createFromFormat('!Y-m-d', $v, $tz);
            } catch (\Throwable) {
                return null;
            }

            return $d && $d->format('Y-m-d') === $v ? $d : null;
        };
        $from = $parse($request->query('done_from'));
        $to = $parse($request->query('done_to'));
        $today = now($tz)->startOfDay();

        if (! $from && ! $to) {
            return ['custom' => false, 'from' => now($tz)->subDays(14), 'to' => now($tz)->endOfDay(), 'label' => 'Completed in the last 14 days'];
        }
        $to = ($to ?? $today)->copy()->min($today);
        $from = ($from ?? $to->copy()->subDays(13))->copy()->min($today);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $label = $from->isSameDay($to)
            ? 'Completed on '.$from->format('M j, Y')
            : 'Completed '.$from->format($from->year === $to->year ? 'M j' : 'M j, Y').' – '.$to->format('M j, Y');

        return ['custom' => true, 'from' => $from->copy()->startOfDay(), 'to' => $to->copy()->endOfDay(), 'label' => $label];
    }

    /** Quick ranges for the Completed list, as [from, to] dates. */
    private function donePresets(): array
    {
        $today = now(config('app.timezone'));
        $d = fn ($c) => $c->format('Y-m-d');
        $lastMonth = $today->copy()->subMonthNoOverflow();

        return ['today' => $d($today), 'presets' => [
            'Last 30 days' => [$d($today->copy()->subDays(29)), $d($today)],
            'Last 90 days' => [$d($today->copy()->subDays(89)), $d($today)],
            'This month' => [$d($today->copy()->startOfMonth()), $d($today)],
            'Last month' => [$d($lastMonth->copy()->startOfMonth()), $d($lastMonth->copy()->endOfMonth())],
            'This year' => [$d($today->copy()->startOfYear()), $d($today)],
        ]];
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
            // Tasks someone dragged into place keep that order; new ones go on top in the usual order (Hotlist, overdue, due date).
            'tasks' => $openTasks->where('assignee_id', $u->id)
                ->sortBy(fn ($t) => [$t->board_sort === null ? 0 : 1, $t->board_sort ?? 0, $t->hot ? 0 : 1, $t->isOverdue() ? 0 : 1, $t->due_on?->timestamp ?? PHP_INT_MAX, -$t->created_at->timestamp])->values(),
            'routines' => $routines->where('assignee_id', $u->id)
                ->sortBy(fn ($r) => [$r->isDone() ? 1 : 0, $cadenceOrder[$r->cadence] ?? 9, $r->sort])->values(),
        ])->values();
    }
}
