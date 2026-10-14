@php($mine = $t->assignee_id === auth()->id())
<div class="task {{ $t->isDone() ? 'done' : '' }} {{ $t->isOverdue() ? 'overdue' : '' }} {{ $mine ? 'mine' : '' }}" data-assignee="{{ $t->assignee_id }}" data-search="{{ strtolower($t->title.' '.$t->details.' '.$t->assignee?->name) }}">
    @can('complete-task', $t)
        <form method="POST" action="{{ route('tasks.toggle', $t) }}">
            @csrf
            <button class="tick" type="submit" aria-pressed="{{ $t->isDone() ? 'true' : 'false' }}" aria-label="{{ $t->isDone() ? 'Reopen' : 'Mark done' }}: {{ $t->title }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
        </form>
    @else
        <span></span>
    @endcan
    <div class="task-body">
        <div class="task-t">
            {{ $t->title }}
            @if($t->hot && ! $t->isDone())<span class="pill hot">Hotlist</span>@endif
        </div>
        @if($t->details)<div class="task-d">{{ $t->details }}</div>@endif
        <div class="rm">
            <span class="who-chip {{ $mine ? 'me-chip' : '' }}">{{ $mine ? 'You' : $t->assignee?->name }}</span>
            @if($t->isDone())
                <span class="ok">✓ {{ $t->completed_at->format('M j, g:ia') }}{{ $t->completer ? ' · '.$t->completer->firstName() : '' }}</span>
            @else
                @if($t->dueLabel())<span class="{{ $t->isOverdue() ? 'late' : '' }}">{{ $t->dueLabel() }}</span>@endif
                <span>from {{ $t->creator?->firstName() ?? '—' }} · {{ $t->created_at->format('M j') }}</span>
            @endif
        </div>
    </div>
    @can('admin')<a class="mini" href="{{ route('tasks.edit', $t) }}">Edit</a>@endcan
</div>
