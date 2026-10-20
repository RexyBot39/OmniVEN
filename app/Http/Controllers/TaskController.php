<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    /** Anyone can give a task to anyone, themselves included. Editing and deleting stay with admins. */
    public function store(Request $request): RedirectResponse
    {
        $task = Task::create($this->validated($request) + [
            'created_by' => $request->user()->id, 'assigned_at' => now(), 'assigned_by' => $request->user()->id,
        ]);
        Activity::log('task.assigned', "Assigned “{$task->title}” to {$task->assignee->name}".($task->hot ? ' (Hotlist)' : ''), $task);

        return $this->back($task->assignee->is($request->user())
            ? "Added “{$task->title}” to your list."
            : "Assigned “{$task->title}” to {$task->assignee->firstName()}.");
    }

    /**
     * A column's open tasks in the order someone dragged them into. Anyone can arrange their own column;
     * admins can arrange anyone's, and dropping a task into another person's column reassigns it.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $me = $request->user();
        $assignee = User::findOrFail($data['assignee_id']);
        abort_unless($me->is_admin || $assignee->is($me), 403);

        $tasks = Task::open()->whereIn('id', $data['ids'])->get()->keyBy('id');
        $moving = $tasks->filter(fn (Task $t) => $t->assignee_id !== $assignee->id);
        abort_if($moving->isNotEmpty() && ! $me->is_admin, 403);

        DB::transaction(function () use ($data, $tasks, $assignee, $me) {
            foreach (array_values($data['ids']) as $i => $id) {
                if ($t = $tasks->get($id)) {
                    $t->fill(['board_sort' => $i + 1]);
                    if ($t->assignee_id !== $assignee->id) {
                        $t->fill(['assignee_id' => $assignee->id, 'assigned_at' => now(), 'assigned_by' => $me->id]);
                    }
                    $t->save();
                }
            }
        });
        foreach ($moving as $t) {
            Activity::log('task.updated', "Moved task “{$t->title}” to {$assignee->name}’s column", $t);
        }

        return response()->json(['ok' => true, 'moved' => $moving->count()]);
    }

    public function edit(Task $task): View
    {
        return view('tasks.form', ['task' => $task, 'people' => $this->people(), 'taskBrands' => Task::brandChoices()]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $task->fill($this->validated($request));
        $reassigned = $task->isDirty('assignee_id');
        if ($reassigned) {
            $task->fill(['assigned_at' => now(), 'assigned_by' => $request->user()->id, 'board_sort' => null]);
        }
        $task->save();
        Activity::log('task.updated', "Edited task “{$task->title}”".($reassigned ? " · reassigned to {$task->assignee->name}" : ''), $task);

        return $this->back('Task saved.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Activity::log('task.deleted', "Deleted task “{$task->title}”", $task);
        $task->delete();

        return $this->back('Task removed.');
    }

    /** The assignee or any admin can mark a task done (or reopen it). */
    public function toggle(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('complete-task', $task);

        if ($task->isDone()) {
            $task->update(['completed_at' => null, 'completed_by' => null]);
            Activity::log('task.reopened', "Reopened task “{$task->title}”", $task);
            $msg = "Reopened “{$task->title}”.";
        } else {
            $task->update(['completed_at' => now(), 'completed_by' => $request->user()->id]);
            $late = $task->due_on && $task->due_on->lt(today()) ? ' (late, due '.$task->due_on->format('M j').')' : '';
            Activity::log('task.completed', "Completed task “{$task->title}”".$late, $task);
            $msg = "Marked “{$task->title}” done. Add the link to what it produced under Finished.";
            // Open its "Add result" box in the finished list, so the link is one paste away.
            session()->flash('outcome_for', $task->id);
        }

        return $request->input('return') === 'hotlist'
            ? redirect()->route('dashboard')->with('status', $msg)
            : $this->back($msg);
    }

    /** What a finished task produced: a short note and a link. Whoever can tick the task can add it. */
    public function outcome(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('complete-task', $task);
        abort_unless($task->isDone(), 422, 'Finish the task first.');
        $data = $request->validate([
            'outcome' => ['nullable', 'string', 'max:300'],
            'outcome_url' => ['nullable', 'url:http,https', 'max:1024'],
        ], ['outcome_url.url' => 'The link must start with http:// or https://.']);
        $task->update(['outcome' => trim((string) ($data['outcome'] ?? '')) ?: null, 'outcome_url' => $data['outcome_url'] ?? null]);
        if ($task->outcome || $task->outcome_url) {
            Activity::log('task.outcome', "Added the result of “{$task->title}”", $task);
        }

        return redirect()->to(route('dashboard').'#tasks')->with(['status' => $task->outcome || $task->outcome_url ? 'Result saved.' : 'Result removed.', 'outcome_saved' => $task->id]);
    }

    private function back(string $msg): RedirectResponse
    {
        return redirect()->to(route('dashboard').'#tasks')->with('status', $msg);
    }

    private function people()
    {
        return User::where('is_active', true)->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:4000'],
            'assignee_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'due_on' => ['nullable', 'date'],
            'brand_id' => ['nullable', Rule::in(Task::brandChoices()->pluck('id')->all())],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')],
        ]);
        $data['brand_id'] = $data['brand_id'] ?? null;
        // Forms without the campaign field leave a task's campaign as it is.
        if (! $request->has('campaign_id')) {
            unset($data['campaign_id']);
        }
        $data['hot'] = $request->boolean('hot');

        return $data;
    }
}
