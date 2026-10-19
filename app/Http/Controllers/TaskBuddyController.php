<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Task;
use App\Models\TaskbuddyRun;
use App\Models\User;
use App\Support\Activity;
use App\Support\Assistant\Assistant;
use App\Support\Assistant\TaskBuddy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * TaskBuddy AI, inside Ask OmniVEN AI: upload a file or paste text, answer any questions, review the
 * drafted tasks and add the ones you keep. Each person sees only their own runs.
 */
class TaskBuddyController extends Controller
{
    public function index(Request $request): View
    {
        TaskBuddy::prune();

        return view('taskbuddy.index', $this->shared($request) + ['run' => null]);
    }

    public function show(Request $request, TaskbuddyRun $run): View
    {
        $this->own($request, $run);

        return view('taskbuddy.index', $this->shared($request) + ['run' => $run]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless(Assistant::configured(), 404);
        $me = $request->user();
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:'.TaskBuddy::MAX_TEXT],
            'file' => ['nullable', 'file', 'max:15360'],
            'note' => ['nullable', 'string', 'max:2000'],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', Rule::in(Task::brandChoices()->pluck('id')->all())],
        ], ['file.max' => 'Files can be up to 15 MB.', 'text.max' => 'That’s too much text at once; split it in two.']);
        $text = trim((string) ($data['text'] ?? ''));
        if (! $request->hasFile('file') && $text === '') {
            throw ValidationException::withMessages(['text' => 'Paste some text or choose a file.']);
        }
        $today = TaskbuddyRun::where('user_id', $me->id)->where('created_at', '>=', now(config('app.timezone'))->startOfDay())->count();
        if ($today >= config('assistant.taskbuddy_daily_limit')) {
            return back()->withInput()->withErrors(['text' => 'You’ve made '.$today.' task lists today, the daily limit. It resets at midnight.']);
        }

        $source = ['kind' => 'text', 'mime' => 'text/plain', 'text' => TaskBuddy::clip($text), 'file' => null, 'name' => null];
        if ($request->hasFile('file')) {
            try {
                [$kind, $mime, $fileText, $bytes] = TaskBuddy::read($request->file('file'));
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['file' => $e->getMessage()]);
            }
            $name = mb_substr($request->file('file')->getClientOriginalName(), 0, 200);
            // Pasted text alongside a file is added to the file's text.
            $source = ['kind' => $kind, 'mime' => $mime, 'text' => $fileText !== null ? trim($fileText."\n\n".$text) : ($text ?: null), 'file' => $bytes, 'name' => $name];
        }

        $run = TaskbuddyRun::create([
            'user_id' => $me->id,
            'assignee_id' => $data['assignee_id'] ?? $me->id,
            'brand_id' => $data['brand_id'] ?? null,
            'title' => $source['name'] ? Str::limit($source['name'], 150) : Str::limit(preg_replace('/\s+/', ' ', $text), 80),
            'source_name' => $source['name'],
            'source_kind' => $source['kind'],
            'source_mime' => $source['mime'],
            'source_text' => $source['text'],
            'source_file' => $source['file'],
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'status' => 'pending',
        ]);
        // Activity shows that TaskBuddy was used, never what was in the file.
        Activity::log('taskbuddy.started', 'Asked TaskBuddy AI for a task list');
        dispatch(fn () => TaskBuddy::process($run))->afterResponse();

        return redirect()->route('taskbuddy.show', $run);
    }

    /** The page checks here until the list (or its questions) is ready. */
    public function status(Request $request, TaskbuddyRun $run): JsonResponse
    {
        $this->own($request, $run);

        return response()->json(['status' => $run->status]);
    }

    /** Answers to its questions, a change request, or "skip the questions". */
    public function answer(Request $request, TaskbuddyRun $run): RedirectResponse
    {
        $this->own($request, $run);
        abort_if($run->isBusy() || $run->status === 'added', 409);
        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:500'],
            'others' => ['nullable', 'array'],
            'others.*' => ['nullable', 'string', 'max:500'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'skip' => ['nullable', 'boolean'],
            'retry' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('skip')) {
            $run->update(['status' => 'ready']);

            return redirect()->route('taskbuddy.show', $run);
        }
        if (! $run->source_text && ! $run->source_file) {
            return back()->withErrors(['feedback' => 'The original is no longer kept (after a week), so TaskBuddy can’t revise this list. Start a new one.']);
        }
        if ($request->boolean('retry') && $run->status === 'error') {
            $run->update(['status' => 'pending', 'error' => null]);
            dispatch(fn () => TaskBuddy::process($run))->afterResponse();

            return redirect()->route('taskbuddy.show', $run);
        }
        // A typed answer wins over a picked choice.
        $answers = [];
        foreach (collect($run->questions ?? [])->pluck('id') as $id) {
            $answers[$id] = trim((string) ($data['others'][$id] ?? '')) ?: trim((string) ($data['answers'][$id] ?? ''));
        }
        $feedback = trim((string) ($data['feedback'] ?? ''));
        if (! array_filter($answers) && $feedback === '') {
            return back()->withErrors(['feedback' => 'Answer a question or say what to change first.']);
        }
        $run->update(['answers' => $answers, 'feedback' => $feedback ?: null, 'status' => 'pending', 'error' => null]);
        dispatch(fn () => TaskBuddy::process($run))->afterResponse();

        return redirect()->route('taskbuddy.show', $run);
    }

    /** Add the tasks kept on the review screen, as edited. */
    public function add(Request $request, TaskbuddyRun $run): RedirectResponse
    {
        $this->own($request, $run);
        abort_unless(in_array($run->status, ['ready', 'questions'], true), 409);
        $request->validate([
            'tasks' => ['required', 'array', 'max:'.TaskBuddy::MAX_TASKS],
            'tasks.*.keep' => ['nullable', 'boolean'],
            'tasks.*.title' => ['required_if:tasks.*.keep,1', 'nullable', 'string', 'max:200'],
            'tasks.*.details' => ['nullable', 'string', 'max:4000'],
            'tasks.*.assignee_id' => ['required_if:tasks.*.keep,1', 'nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'tasks.*.brand_id' => ['nullable', Rule::in(Task::brandChoices()->pluck('id')->all())],
            'tasks.*.due_on' => ['nullable', 'date'],
            'tasks.*.hot' => ['nullable', 'boolean'],
            'tasks.*.campaign_id' => ['nullable', Rule::exists('campaigns', 'id')],
        ], ['tasks.*.title.required_if' => 'Every task you keep needs a title.']);
        $me = $request->user();
        $kept = collect($request->input('tasks'))->filter(fn ($t) => ! empty($t['keep']));
        if ($kept->isEmpty()) {
            return back()->withErrors(['tasks' => 'Tick at least one task to add.']);
        }
        $ids = [];
        foreach ($kept as $t) {
            $task = Task::create([
                'title' => trim($t['title']),
                'details' => trim((string) ($t['details'] ?? '')) ?: null,
                'assignee_id' => (int) $t['assignee_id'],
                'brand_id' => $t['brand_id'] ?? null ?: null,
                'due_on' => $t['due_on'] ?? null ?: null,
                'hot' => ! empty($t['hot']),
                'campaign_id' => $t['campaign_id'] ?? null ?: null,
                'created_by' => $me->id, 'assigned_at' => now(), 'assigned_by' => $me->id,
            ]);
            $ids[] = $task->id;
            Activity::log('task.assigned', "Assigned “{$task->title}” to {$task->assignee->name} (TaskBuddy AI)".($task->hot ? ' (Hotlist)' : ''), $task);
        }
        $run->update(['status' => 'added', 'task_ids' => $ids]);
        $run->forgetSource();
        $people = User::whereIn('id', $kept->pluck('assignee_id')->unique())->get();
        $forWho = $people->count() === 1 && $people->first()->is($me) ? 'your list' : $people->map->firstName()->join(', ', ' and ');

        return redirect()->to(route('dashboard').'#tasks')->with('status', 'TaskBuddy AI added '.count($ids).' '.Str::plural('task', count($ids)).' to '.$forWho.'.');
    }

    public function destroy(Request $request, TaskbuddyRun $run): RedirectResponse
    {
        $this->own($request, $run);
        $run->delete();

        return redirect()->route('taskbuddy')->with('status', 'Deleted.');
    }

    private function own(Request $request, TaskbuddyRun $run): void
    {
        abort_unless($run->user_id === $request->user()->id, 404);
    }

    private function shared(Request $request): array
    {
        $me = $request->user();

        return [
            'configured' => Assistant::configured(),
            'isAdmin' => $me->can('admin'),
            'runs' => TaskbuddyRun::where('user_id', $me->id)->latest()->limit(20)->get(),
            'people' => User::where('is_active', true)->orderBy('name')->get(),
            'taskBrands' => Task::brandChoices(),
            'campaigns' => Campaign::where('ends_on', '>=', now(config('app.timezone'))->subDays(14)->format('Y-m-d'))->orderBy('starts_on')->get(),
            'accept' => TaskBuddy::accept(),
        ];
    }
}
