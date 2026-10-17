<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\RedditThread;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity;
use App\Support\Reddit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reddit opportunities: threads worth a reply, best first. Anyone can make one a task or skip it. */
class RedditController extends Controller
{
    public function index(Request $request): View
    {
        Reddit::refreshIfStale();
        $kind = array_key_exists((string) $request->query('kind'), RedditThread::KINDS) ? $request->query('kind') : null;
        $open = RedditThread::with('brand')->where('status', 'new')->where('posted_at', '>=', now()->subDays(config('reddit.max_age_days')));

        return view('reddit.index', [
            'configured' => Reddit::configured(),
            'status' => Reddit::status(),
            'threads' => (clone $open)->when($kind, fn ($q) => $q->where('kind', $kind))->orderByDesc('score')->orderByDesc('posted_at')->limit(50)->get(),
            'counts' => (clone $open)->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind'),
            'kind' => $kind,
            'people' => User::where('is_active', true)->orderBy('name')->get(),
            'recent' => RedditThread::with(['task.assignee', 'user'])->where('status', '!=', 'new')->latest('updated_at')->limit(10)->get(),
        ]);
    }

    public function task(Request $request, RedditThread $thread): RedirectResponse
    {
        $data = $request->validate(['assignee_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)]]);
        if ($thread->status === 'tasked') {
            return back()->with('status', 'Someone already made that a task.');
        }
        $me = $request->user();
        $brand = $thread->brand ?? Brand::where('slug', '39dg')->first();
        $brandOk = $brand && Task::brandChoices()->pluck('id')->contains($brand->id);
        $task = Task::create([
            'title' => Str::limit('Reply on r/'.$thread->subreddit.': “'.$thread->title.'”', 195),
            'details' => $this->details($thread, $brand),
            'assignee_id' => $data['assignee_id'],
            'brand_id' => $brandOk ? $brand->id : null,
            'hot' => $thread->kind === 'complaint',
            'due_on' => CarbonImmutable::now(config('app.timezone'))->format('Y-m-d'),
            'created_by' => $me->id, 'assigned_at' => now(), 'assigned_by' => $me->id,
        ]);
        $thread->update(['status' => 'tasked', 'task_id' => $task->id, 'user_id' => $me->id]);
        Activity::log('reddit.tasked', "Made a Reddit reply task for {$task->assignee->name}: r/{$thread->subreddit}", $task);

        return back()->with('status', $task->assignee->is($me) ? 'Added to your tasks, due today.' : "Assigned to {$task->assignee->firstName()}, due today.");
    }

    public function skip(Request $request, RedditThread $thread): RedirectResponse
    {
        $thread->update(['status' => 'skipped', 'user_id' => $request->user()->id]);

        return back()->with('status', 'Skipped.');
    }

    public function restore(RedditThread $thread): RedirectResponse
    {
        abort_unless($thread->status === 'skipped', 422);
        $thread->update(['status' => 'new', 'user_id' => null]);

        return back()->with('status', 'Back in the list.');
    }

    /** The thread link and how to reply, by kind. Reddit and FTC rules: say you work for the brand. */
    private function details(RedditThread $t, ?Brand $brand): string
    {
        $name = $brand?->name ?? '39DollarGlasses';
        $competitors = collect($t->matched)->intersect(config('reddit.competitors'))->map(fn ($c) => Str::title($c))->implode(', ');
        $how = match ($t->kind) {
            'complaint' => "An unhappy customer. Reply the same day from the {$name} account: apologise, don't argue, and ask them to message their order number privately or to contact customer service. Never ask for order or personal details in public. Tell customer service so they follow up.",
            'mention' => "Someone mentioned {$name}. Thank them, correct anything wrong, and add something useful (a fitting tip, the return policy). Say you work for {$name}.",
            'recommend' => 'Someone is asking about '.($competitors ?: 'other glasses sites').". Answer their question first, honestly. Mention {$name} only where it fits and say you work there. Check r/{$t->subreddit}'s rules on self-promotion; leave out links if they're not allowed.",
            default => "A buying question we can answer. Answer it fully first. Mention {$name} only where it genuinely helps, and say you work there. Check r/{$t->subreddit}'s rules on self-promotion.",
        };

        return $t->url."\n\n".$how."\n\nWhen you've replied, add the link to your comment as this task's result.";
    }
}
