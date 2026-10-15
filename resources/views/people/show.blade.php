@extends('layouts.app', ['title' => $person->name])

@section('content')
<main class="wrap staff">
    <a href="{{ route('people.index') }}" class="back">← People</a>

    <header class="staff-head">
        <div class="staff-id">
            @if($person->avatar)<img src="{{ $person->avatar }}" alt="" width="56" height="56" referrerpolicy="no-referrer">@else<span class="staff-ini">{{ strtoupper(mb_substr($person->name, 0, 1)) }}</span>@endif
            <div>
                <h1 class="form-h">{{ $person->name }}</h1>
                <p class="sub">{{ $person->email }} · {{ $person->is_admin ? 'Admin' : 'Staff' }}{{ $person->is_active ? '' : ' · access turned off' }} · {{ $person->last_login_at ? 'last signed in '.$person->last_login_at->format('M j, g:ia') : 'not signed in yet' }}</p>
            </div>
        </div>
        <div class="staff-switch">
            <label for="p" class="sub">Show another person</label>
            <select id="p" data-nav>
                @foreach($people as $p)<option value="{{ route('people.show', $p) }}" @selected($p->is($person))>{{ $p->name }}</option>@endforeach
            </select>
        </div>
    </header>

    <div class="staff-stats">
        <div class="{{ $stats['routines_due'] ? 'warn' : 'good' }}"><b>{{ $stats['routines_due'] }}</b><span>of {{ $stats['routines_total'] }} assigned routines still due</span></div>
        <div><b>{{ $stats['tasks_open'] }}</b><span>open {{ \Illuminate\Support\Str::plural('task', $stats['tasks_open']) }}</span></div>
        <div class="{{ $stats['tasks_overdue'] ? 'bad' : '' }}"><b>{{ $stats['tasks_overdue'] }}</b><span>overdue</span></div>
        <div class="good"><b>{{ $stats['done_week'] }}</b><span>routines and tasks done in the last 7 days</span></div>
    </div>

    <div class="staff-cols">
        <section>
            <div class="h2row"><h2>Assigned routines</h2><a class="mini" href="{{ route('work.create', ['type' => 'routine', 'assignee' => $person->id, 'from' => 'person']) }}">+ Assign a routine</a></div>
            @forelse($byCadence as $c)
                <div class="staff-cad">
                    <div class="cat-h">{{ $c['label'] }}</div>
                    <ul class="staff-list">
                        @foreach($c['routines'] as $r)
                            @php($done = $r->isDone())
                            <li class="{{ $done ? 'done' : 'due' }}">
                                <span class="st">{{ $done ? '✓ Done' : 'Due' }}</span>
                                <div class="what"><b>{{ $r->title }}</b>
                                    <span class="sub">
                                        @if($done){{ $r->latestCompletion->completed_at->format('M j, g:ia') }}{{ $r->latestCompletion->user && ! $r->latestCompletion->user->is($person) ? ' · ticked by '.$r->latestCompletion->user->firstName() : '' }}
                                        @elseif($r->latestCompletion)Last done {{ $r->latestCompletion->completed_at->format('M j') }}
                                        @else Never done yet @endif
                                        · {{ implode(', ', $r->brandLabels()) }}
                                    </span>
                                    @include('partials.stamps', ['assignedAt' => $r->assigned_at, 'assignedBy' => $r->assigner, 'doneAt' => $r->latestCompletion?->completed_at, 'doneBy' => $r->latestCompletion?->user, 'doneLabel' => $done ? 'Done' : 'Last done'])
                                </div>
                                <a class="mini" href="{{ route('routines.edit', $r) }}">Edit</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="empty">No routines are assigned to {{ $person->firstName() }}. Edit a routine and choose them under “Assigned to”.</div>
            @endforelse
        </section>

        <section>
            <div class="h2row"><h2>Open tasks</h2><a class="mini" href="{{ route('work.create', ['type' => 'task', 'assignee' => $person->id, 'from' => 'person']) }}">+ Assign a task</a></div>
            @if($openTasks->isEmpty())
                <div class="empty">No open tasks for {{ $person->firstName() }}.</div>
            @else
                <ul class="staff-list">
                    @foreach($openTasks as $t)
                        <li class="{{ $t->isOverdue() ? 'late' : 'due' }}">
                            <span class="st">{{ $t->hot ? 'Hot' : ($t->isOverdue() ? 'Late' : 'Open') }}</span>
                            <div class="what"><b>{{ $t->title }}</b>
                                <span class="sub">{{ $t->dueLabel() ?? 'No due date' }}{{ ($t->assigner ?? $t->creator) ? ' · from '.($t->assigner ?? $t->creator)->firstName() : '' }}</span>
                                @include('partials.stamps', ['assignedAt' => $t->assigned_at ?? $t->created_at, 'assignedBy' => $t->assigner ?? $t->creator])
                            </div>
                            <a class="mini" href="{{ route('tasks.edit', $t) }}">Edit</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="staff-recent">
        <div class="h2row"><h2>Done in the last 30 days</h2><span class="sub">{{ $ticks->count() }} routine {{ \Illuminate\Support\Str::plural('tick', $ticks->count()) }} · {{ $doneTasks->count() }} {{ \Illuminate\Support\Str::plural('task', $doneTasks->count()) }}</span></div>
        @php($recent = $ticks->map(fn ($c) => ['at' => $c->completed_at, 'kind' => 'Routine', 'title' => $c->routine?->title ?? 'A removed routine', 'note' => null])
            ->concat($doneTasks->map(fn ($t) => ['at' => $t->completed_at, 'kind' => 'Task', 'title' => $t->title, 'note' => $t->assignee && ! $t->assignee->is($person) ? 'for '.$t->assignee->firstName() : null]))
            ->sortByDesc('at')->values())
        @if($recent->isEmpty())
            <div class="empty">Nothing ticked off in the last 30 days.</div>
        @else
            <ul class="staff-feed">
                @foreach($recent as $e)
                    <li><span class="when" title="{{ $e['at']->timezone(config('app.timezone'))->format('M j, Y g:ia') }}">{{ $e['at']->timezone(config('app.timezone'))->format('M j g:ia') }}</span><span class="kind k-{{ strtolower($e['kind']) }}">{{ $e['kind'] }}</span><span class="t">{{ $e['title'] }}@if($e['note']) <span class="sub">{{ $e['note'] }}</span>@endif</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="sub staff-private">{{ $person->firstName() }}'s sticky notes are private and never shown here.</p>
</main>
@endsection
