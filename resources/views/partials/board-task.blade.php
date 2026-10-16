{{-- One open task in a person's column, colored by brand. --}}
@php($bc = $t->brand ? 'var(--c'.$t->brand->colorSlot().')' : 'var(--line)')
<div class="bt task {{ $t->isOverdue() ? 'overdue' : '' }} {{ $t->hot ? 'is-hot' : '' }}" style="--bc:{{ $bc }}" data-assignee="{{ $t->assignee_id }}" data-brands="{{ $t->brand?->slug ?? 'all' }}" data-search="{{ strtolower($t->title.' '.$t->details.' '.$t->assignee?->name.' '.$t->brand?->name) }}">
    @can('complete-task', $t)
        <form method="POST" action="{{ route('tasks.toggle', $t) }}">@csrf
            <button class="tick" type="submit" aria-pressed="false" aria-label="Mark done: {{ $t->title }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
        </form>
    @else
        <span></span>
    @endcan
    <div class="bt-body">
        <div class="bt-t">{{ $t->title }}</div>
        @if($t->details)<div class="bt-d">{{ $t->details }}</div>@endif
        <div class="bt-m">
            @if($t->brand)<span class="bpill" style="--bc:{{ $bc }}">{{ $t->brand->name }}</span>@endif
            @if($t->hot)<span class="pill hot">Hotlist</span>@endif
            @if($t->dueLabel())<span class="{{ $t->isOverdue() ? 'late' : '' }}">{{ $t->dueLabel() }}</span>@endif
            @php($from = $t->assigner ?? $t->creator)
            @if($from && ! $from->is($t->assignee))<span>from {{ $from->firstName() }}</span>@endif
        </div>
        @can('admin')@include('partials.stamps', ['assignedAt' => $t->assigned_at ?? $t->created_at, 'assignedBy' => $t->assigner ?? $t->creator, 'doneAt' => null, 'doneBy' => null])@endcan
    </div>
    @can('admin')<a class="mini" href="{{ route('tasks.edit', $t) }}">Edit</a>@endcan
</div>
