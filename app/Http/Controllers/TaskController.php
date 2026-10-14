<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    /** Admins assign tasks to anyone, including other admins. */
    public function store(Request $request): RedirectResponse
    {
        $task = Task::create($this->validated($request) + ['created_by' => $request->user()->id]);
        Activity::log('task.assigned', "Assigned “{$task->title}” to {$task->assignee->name}".($task->hot ? ' (Hotlist)' : ''), $task);

        return $this->back("Assigned “{$task->title}” to {$task->assignee->firstName()}.");
    }

    public function edit(Task $task): View
    {
        return view('tasks.form', ['task' => $task, 'people' => $this->people()]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $task->update($this->validated($request));
        Activity::log('task.updated', "Edited task “{$task->title}”", $task);

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
            $msg = "Marked “{$task->title}” done.";
        }

        return $request->input('return') === 'hotlist'
            ? redirect()->route('dashboard')->with('status', $msg)
            : $this->back($msg);
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
        ]);
        $data['hot'] = $request->boolean('hot');

        return $data;
    }
}
