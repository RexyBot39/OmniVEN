<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('people.index', ['users' => User::orderByDesc('is_admin')->orderBy('name')->get()]);
    }

    /** Admins: one person's routines and tasks. Their PostIT notes stay private and are never shown. */
    public function show(User $user): View
    {
        $routines = Routine::with(['brands', 'latestCompletion.user', 'assigner'])->where('assignee_id', $user->id)
            ->orderBy('sort')->orderBy('title')->get();
        $byCadence = collect(config('omniven.cadences'))
            ->map(fn ($label, $key) => ['label' => $label, 'routines' => $routines->where('cadence', $key)->values()])
            ->filter(fn ($c) => $c['routines']->isNotEmpty());

        $openTasks = Task::with(['creator', 'assigner'])->open()->where('assignee_id', $user->id)
            ->orderByDesc('hot')->orderByRaw('due_on is null')->orderBy('due_on')->latest()->get();
        $doneTasks = Task::with(['assignee', 'assigner', 'completer'])->whereNotNull('completed_at')->where('completed_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->where('assignee_id', $user->id)->orWhere('completed_by', $user->id))
            ->latest('completed_at')->limit(50)->get();
        $ticks = RoutineCompletion::with('routine')->where('user_id', $user->id)
            ->where('completed_at', '>=', now()->subDays(30))->latest('completed_at')->limit(50)->get();

        $weekAgo = now()->subDays(7);
        $stats = [
            'routines_due' => $routines->reject->isDone()->count(),
            'routines_total' => $routines->count(),
            'tasks_open' => $openTasks->count(),
            'tasks_overdue' => $openTasks->filter->isOverdue()->count(),
            'done_week' => $ticks->where('completed_at', '>=', $weekAgo)->count() + $doneTasks->where('completed_at', '>=', $weekAgo)->count(),
        ];

        return view('people.show', [
            'person' => $user, 'byCadence' => $byCadence, 'openTasks' => $openTasks, 'doneTasks' => $doneTasks,
            'ticks' => $ticks, 'stats' => $stats,
            'people' => User::where('is_active', true)->orWhere('id', $user->id)->orderBy('name')->get(),
        ]);
    }

    /** Add someone before their first sign-in, so tasks can be assigned to them. */
    public function store(Request $request): RedirectResponse
    {
        $domain = config('omniven.allowed_domain');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'ends_with:@'.$domain, 'unique:users,email'],
            'name' => ['required', 'string', 'max:120'],
        ], ['email.ends_with' => "Only @{$domain} addresses can be added."]);

        $user = User::create([
            'email' => strtolower($data['email']),
            'name' => $data['name'],
            'is_admin' => $request->boolean('is_admin'),
            'is_active' => true,
        ]);

        Activity::log('person.added', "Added {$user->name} ({$user->email})".($user->is_admin ? ' as admin' : ''), $user);

        return back()->with('status', "Added {$user->name}. They can sign in with Google now.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data = array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $data);

        if ($user->is($request->user())) {
            return back()->with('error', 'You can\'t change your own access.');
        }
        if ($user->isConfiguredAdmin() && (($data['is_admin'] ?? true) === false || ($data['is_active'] ?? true) === false)) {
            return back()->with('error', "{$user->name} is a permanent admin set in the server settings (OMNIVEN_ADMIN_EMAILS).");
        }

        $user->update($data);
        $what = array_key_exists('is_admin', $data) ? ($data['is_admin'] ? 'made admin' : 'made staff') : ($data['is_active'] ? 'access restored' : 'access turned off');
        Activity::log('person.updated', "{$user->name}: {$what}", $user);

        return back()->with('status', "Updated {$user->name}.");
    }
}
