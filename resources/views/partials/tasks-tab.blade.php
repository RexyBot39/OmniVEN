{{-- ================= TASKS: the main board =================
     One column per marketing person, then anyone else with open work, then you. Each column holds the
     person's open tasks (colored by brand) and the routines assigned to them for this period (colored as
     routines). Everything finished in the last 14 days is crossed out below. --}}
@php($me = auth()->user())
<section data-view="tasks">
    <div class="h2row">
        <h2>Tasks</h2>@include('partials.star', ['key' => 'page:2', 'label' => 'the Tasks tab'])
        <div class="tb-legend" aria-label="Color key">
            @foreach($taskBrands as $b)<span><i style="background:var(--c{{ $b->colorSlot() }})"></i>{{ $b->name }}</span>@endforeach
            <span><i class="tb-routine"></i>Routine</span>
        </div>
        <div class="h2-tools">
            @can('admin')<button type="button" class="seg-b solo" data-stamps aria-pressed="false" title="Show when each task was assigned and finished">Show times</button>@endcan
            <div class="seg" role="group" aria-label="Show">
                <button type="button" class="seg-b" data-taskfilter="mine" aria-pressed="false">Mine</button>
                <button type="button" class="seg-b" data-taskfilter="all" aria-pressed="true">Everyone</button>
            </div>
            <a class="btn" href="{{ route('templates.index') }}">From a template</a>
            <a class="btn primary" href="{{ route('work.create', ['type' => 'task']) }}">{{ $me->can('admin') ? '+ New task or routine' : '+ New task' }}</a>
        </div>
    </div>

    <div class="board">
        @foreach($board as $col)
            @php($u = $col['user'])
            @php($due = $col['routines']->reject->isDone()->count())
            <div class="bcol {{ $u->is($me) ? 'mine' : '' }}" data-person="{{ $u->id }}">
                <div class="bcol-h">
                    <span class="bcol-av">@if($u->avatar)<img src="{{ $u->avatar }}" alt="" width="34" height="34" referrerpolicy="no-referrer">@else{{ strtoupper(mb_substr($u->name, 0, 1)) }}@endif</span>
                    <div>
                        <h3>{{ $u->is($me) ? $u->firstName().' (you)' : $u->firstName() }}</h3>
                        <span class="sub">{{ $col['tasks']->count() }} {{ \Illuminate\Support\Str::plural('task', $col['tasks']->count()) }} · {{ $due }} {{ \Illuminate\Support\Str::plural('routine', $due) }} due</span>
                        @if($u->is($me))<a class="mini myday-link" href="{{ route('my-day') }}">Morning Slack summary: {{ ($me->myday_slack ?? true) ? 'on' : 'off' }}</a>@endif
                    </div>
                </div>
                <form method="POST" action="{{ route('tasks.store') }}" class="bcol-add">
                    @csrf
                    <input type="hidden" name="assignee_id" value="{{ $u->id }}">
                    <input type="text" name="title" required maxlength="200" placeholder="{{ $u->is($me) ? 'Add a task for yourself…' : 'Add a task for '.$u->firstName().'…' }}" aria-label="New task for {{ $u->name }}">
                    <div class="bcol-add-row">
                        <select name="brand_id" aria-label="Brand">
                            <option value="">No brand</option>
                            @foreach($taskBrands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                        </select>
                        <input type="date" name="due_on" aria-label="Due date">
                        <button class="btn" type="submit">Add</button>
                    </div>
                </form>
                <div class="bcol-list">
                    @foreach($col['tasks'] as $t)
                        @include('partials.board-task', ['t' => $t])
                    @endforeach
                    @foreach($col['routines'] as $r)
                        @include('partials.board-routine', ['r' => $r])
                    @endforeach
                    @if($col['tasks']->isEmpty() && $col['routines']->isEmpty())
                        <p class="sub bcol-empty">Nothing open.</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="tb-done" id="done-list">
        <div class="h2row">
            <h3>{{ $doneRange['label'] }}</h3>
            <span class="sub">{{ $doneTasks->count() }}{{ $doneRange['more'] ? '+' : '' }} {{ \Illuminate\Support\Str::plural('task', $doneTasks->count()) }}, everyone’s</span>
            @if($doneRange['custom'])<a class="mini" href="{{ route('dashboard') }}#tasks">Back to the last 14 days</a>@endif
        </div>
        <form class="done-range" method="GET" action="{{ route('dashboard') }}#tasks" aria-label="Show completed tasks from a date range">
            <label>From <input type="date" name="done_from" value="{{ $doneRange['custom'] ? $doneRange['from']->format('Y-m-d') : '' }}" max="{{ $doneRange['today'] }}"></label>
            <label>To <input type="date" name="done_to" value="{{ $doneRange['custom'] ? $doneRange['to']->format('Y-m-d') : '' }}" max="{{ $doneRange['today'] }}"></label>
            <button class="btn" type="submit">Show</button>
            <span class="done-presets">
                @foreach($doneRange['presets'] as $name => [$f, $t])
                    <a href="{{ route('dashboard', ['done_from' => $f, 'done_to' => $t]) }}#tasks" @if($doneRange['custom'] && $doneRange['from']->format('Y-m-d') === $f && $doneRange['to']->format('Y-m-d') === $t) aria-current="true" @endif>{{ $name }}</a>
                @endforeach
            </span>
        </form>
        @if($doneRange['more'])<p class="sub">Showing the latest 500. Pick a shorter range to see the rest.</p>@endif
        @if($doneTasks->isEmpty())
            <p class="sub">{{ $doneRange['custom'] ? 'No tasks were finished in that range.' : 'Finished tasks show here, crossed out, for two weeks.' }}</p>
        @else
            <ul class="tb-done-list">
                @foreach($doneTasks as $t)
                    <li class="task done {{ $t->assignee_id === $me->id ? 'mine-item' : '' }}" @if(session('outcome_saved') === $t->id || (int) old('outcome_task') === $t->id) data-scroll-here @endif data-assignee="{{ $t->assignee_id }}" data-brands="{{ $t->brand?->slug ?? 'all' }}" data-search="{{ strtolower($t->title.' '.$t->details.' '.$t->outcome.' '.$t->assignee?->name.' '.$t->brand?->name) }}" style="--bc:{{ $t->brand ? 'var(--c'.$t->brand->colorSlot().')' : 'var(--line)' }}">
                        @can('complete-task', $t)
                            <form method="POST" action="{{ route('tasks.toggle', $t) }}">@csrf
                                <button class="tick" type="submit" aria-pressed="true" aria-label="Reopen: {{ $t->title }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                            </form>
                        @else
                            <span class="tick-static" aria-hidden="true">✓</span>
                        @endcan
                        <span class="tb-done-t"><s>{{ $t->title }}</s></span>
                        <span class="tb-done-m">
                            @if($t->brand)<span class="bpill" style="--bc:var(--c{{ $t->brand->colorSlot() }})">{{ $t->brand->name }}</span>@endif
                            <span class="ok">✓ Done{{ $t->completer ? ' · '.$t->completer->firstName() : '' }}</span>
                            <span>{{ $t->assignee?->is($t->completer) ? '' : 'for '.$t->assignee?->firstName().' · ' }}{{ $t->completed_at->timezone(config('app.timezone'))->format($t->completed_at->year === now()->year ? 'D M j' : 'M j, Y') }}</span>
                        </span>
                        @include('partials.task-outcome', ['t' => $t])
                        @can('admin')<span></span>@include('partials.stamps', ['assignedAt' => $t->assigned_at ?? $t->created_at, 'assignedBy' => $t->assigner ?? $t->creator, 'doneAt' => $t->completed_at, 'doneBy' => $t->completer])@endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
