<?php

namespace App\Http\Controllers;

use App\Models\ContentGap;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity;
use App\Support\Seo\ContentGaps;
use App\Support\Seo\SearchConsole;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Content gaps: searches and AI questions that need a page, a better page, or a better title. Anyone can work the queue. */
class ContentGapController extends Controller
{
    public function index(Request $request): View
    {
        // Summaries saved before gaps existed have none: fetch again now rather than wait 6 hours.
        if (SearchConsole::configured() && SearchConsole::brands()->keys()->contains(fn ($slug) => ($s = SearchConsole::summary($slug)) && ! array_key_exists('gap_rows', $s))) {
            Cache::forget('seo:last');
        }
        SearchConsole::refreshIfStale();

        $all = ContentGaps::open();
        $brands = $all->pluck('brand')->filter()->unique('id')->sortBy('sort')->values();
        $brand = $brands->firstWhere('slug', $request->query('brand'));
        $kind = array_key_exists((string) $request->query('kind'), ContentGaps::KINDS) ? $request->query('kind') : null;
        $gaps = $all->when($brand, fn ($c) => $c->filter(fn ($g) => $g['brand']?->is($brand)))
            ->when($kind, fn ($c) => $c->where('kind', $kind))->values();

        return view('content-gaps.index', [
            'gaps' => $gaps->take(60),
            'more' => max(0, $gaps->count() - 60),
            'total' => $all->count(),
            'counts' => $all->countBy('kind'),
            'brands' => $brands,
            'brand' => $brand,
            'kind' => $kind,
            'searchOn' => SearchConsole::configured(),
            'status' => SearchConsole::status(),
            'people' => User::where('is_active', true)->orderBy('name')->get(),
            'recent' => ContentGap::with(['task.assignee', 'brand', 'user'])->latest('updated_at')->limit(12)->get(),
            'dueDefault' => CarbonImmutable::now(config('app.timezone'))->addDays(7)->format('Y-m-d'),
        ]);
    }

    /** Make a gap into a task (title, details, brand filled in) for the person picked. */
    public function task(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'size:40'],
            'assignee_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'due_on' => ['nullable', 'date'],
        ]);
        $gap = ContentGaps::find($data['key']);
        if (! $gap) {
            return back()->with('status', 'That gap is gone; the numbers have changed since the page loaded.');
        }
        $me = $request->user();
        $brandOk = $gap['brand'] && Task::brandChoices()->pluck('id')->contains($gap['brand']->id);
        $task = Task::create([
            'title' => $gap['title'], 'details' => $gap['details'], 'assignee_id' => $data['assignee_id'],
            'brand_id' => $brandOk ? $gap['brand']->id : null, 'due_on' => $data['due_on'] ?? null,
            'created_by' => $me->id, 'assigned_at' => now(), 'assigned_by' => $me->id,
        ]);
        $this->decide($gap, 'tasked', $task->id, $me->id);
        Activity::log('gap.tasked', "Made a task from a content gap: “{$task->title}” for {$task->assignee->name}", $task);

        return back()->with('status', $task->assignee->is($me) ? 'Added to your tasks.' : "Assigned to {$task->assignee->firstName()}.");
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $gap = ContentGaps::find((string) $request->validate(['key' => ['required', 'string', 'size:40']])['key']);
        if ($gap) {
            $this->decide($gap, 'dismissed', null, $request->user()->id);
            Activity::log('gap.dismissed', "Set aside a content gap: “{$gap['query']}”");
        }

        return back()->with('status', 'Set aside. It won’t come back unless you restore it below.');
    }

    /** Put a set-aside gap back in the queue. Gaps made into tasks stay with their task. */
    public function restore(ContentGap $gap): RedirectResponse
    {
        abort_unless($gap->status === 'dismissed', 422);
        $gap->delete();

        return back()->with('status', 'Back in the queue.');
    }

    private function decide(array $gap, string $status, ?int $taskId, int $userId): void
    {
        ContentGap::updateOrCreate(['key' => $gap['key']], [
            'kind' => $gap['kind'], 'brand_id' => $gap['brand']?->id, 'query' => mb_substr($gap['query'], 0, 300),
            'status' => $status, 'task_id' => $taskId, 'user_id' => $userId,
        ]);
    }
}
