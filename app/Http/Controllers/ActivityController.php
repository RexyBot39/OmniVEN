<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin activity report. Only people in OMNIVEN_ACTIVITY_VIEWERS can open it. */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        [$start, $end] = $this->week($request);
        $rows = $this->summary($start, $end);

        $feed = ActivityLog::with('user')
            ->whereBetween('created_at', [$start, $end])
            ->when($request->integer('person'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('cat'), function ($q, $cat) {
                $actions = ActivityLog::CATEGORIES[$cat]['actions'] ?? null;
                $actions ? $q->whereIn('action', $actions) : null;
            })
            ->when(! $request->boolean('visits'), fn ($q) => $q->where('action', '!=', 'visited'))
            ->latest('created_at')->latest('id')
            ->paginate(100)->withQueryString();

        $totals = [
            'active' => $rows->where('days_active', '>', 0)->count(),
            'people' => $rows->count(),
            'tasks_done' => $rows->sum('tasks_done'),
            'routines' => $rows->sum('routines'),
            'content' => $rows->sum('content'),
            'overdue' => $rows->sum('overdue'),
        ];

        return view('activity.index', [
            'start' => $start, 'end' => $end, 'rows' => $rows, 'feed' => $feed, 'totals' => $totals,
            'days' => collect(range(0, 6))->map(fn ($i) => $start->addDays($i)),
            'people' => User::orderBy('name')->get(),
            'isCurrentWeek' => $start->isSameDay(CarbonImmutable::now()->startOfWeek()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->week($request);
        $isLog = $request->input('type') === 'log';
        $name = 'omniven-'.($isLog ? 'activity-log' : 'weekly-summary').'-'.$start->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($start, $end, $isLog) {
            $out = fopen('php://output', 'w');
            if ($isLog) {
                fputcsv($out, ['Timestamp (ET)', 'Person', 'Email', 'Action', 'Details']);
                ActivityLog::with('user')->whereBetween('created_at', [$start, $end])->orderBy('created_at')
                    ->chunk(500, function ($logs) use ($out) {
                        foreach ($logs as $l) {
                            fputcsv($out, [$l->created_at->format('Y-m-d H:i:s'), $l->user?->name, $l->user?->email, $l->action, $l->description]);
                        }
                    });
            } else {
                fputcsv($out, ['Week of', 'Person', 'Email', 'Role', 'Days active', 'Sign-ins', 'Tasks completed', 'Open tasks', 'Overdue tasks', 'Routines ticked', 'Content added/edited', 'PostIT check-offs', 'Total actions', 'Last active (ET)']);
                foreach ($this->summary($start, $end) as $r) {
                    fputcsv($out, [$start->format('Y-m-d'), $r['user']->name, $r['user']->email, $r['user']->is_admin ? 'Admin' : 'Staff',
                        $r['days_active'], $r['sign_ins'], $r['tasks_done'], $r['open'], $r['overdue'], $r['routines'], $r['content'],
                        $r['postit_done'], $r['total'], $r['last_active']?->format('Y-m-d H:i')]);
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Monday 00:00 to Sunday 23:59:59 in the app's timezone (Eastern). */
    private function week(Request $request): array
    {
        $tz = config('app.timezone');
        try {
            $start = $request->filled('week')
                ? CarbonImmutable::parse($request->input('week'), $tz)->startOfWeek()
                : CarbonImmutable::now($tz)->startOfWeek();
        } catch (\Throwable) {
            $start = CarbonImmutable::now($tz)->startOfWeek();
        }

        return [$start, $start->endOfWeek()];
    }

    private function summary(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $logs = ActivityLog::whereBetween('created_at', [$start, $end])->whereNotNull('user_id')
            ->get(['user_id', 'action', 'created_at'])->groupBy('user_id');
        $lastEver = ActivityLog::whereNotNull('user_id')->selectRaw('user_id, max(created_at) as last_at')
            ->groupBy('user_id')->pluck('last_at', 'user_id');
        $openTasks = Task::open()->get(['assignee_id', 'due_on'])->groupBy('assignee_id');

        $content = ActivityLog::CATEGORIES['content']['actions'];

        return User::where('is_active', true)->orWhereIn('id', $logs->keys())->orderBy('name')->get()
            ->map(function (User $u) use ($logs, $lastEver, $openTasks, $content, $start) {
                $mine = $logs->get($u->id, collect());
                $byDay = collect(range(0, 6))->map(fn ($i) => $mine->filter(
                    fn ($l) => $l->created_at->copy()->setTimezone(config('app.timezone'))->isSameDay($start->addDays($i))
                )->count());
                $open = $openTasks->get($u->id, collect());

                return [
                    'user' => $u,
                    'by_day' => $byDay,
                    'days_active' => $byDay->filter()->count(),
                    'sign_ins' => $mine->where('action', 'signed_in')->count(),
                    'tasks_done' => $mine->where('action', 'task.completed')->count(),
                    'routines' => $mine->where('action', 'routine.done')->count(),
                    'content' => $mine->whereIn('action', $content)->count(),
                    'postit_done' => $mine->where('action', 'postit.item_done')->count(),
                    'total' => $mine->where('action', '!=', 'visited')->count(),
                    'open' => $open->count(),
                    'overdue' => $open->filter(fn ($t) => $t->due_on && $t->due_on->lt(today()))->count(),
                    'last_active' => isset($lastEver[$u->id]) ? \Carbon\Carbon::parse($lastEver[$u->id])->setTimezone(config('app.timezone')) : null,
                ];
            })
            ->sortBy([['days_active', 'desc'], ['total', 'desc']])->values();
    }
}
