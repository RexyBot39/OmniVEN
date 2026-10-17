@extends('layouts.app', ['title' => $c->name])

@section('content')
<main class="wrap sales outreach">
    <a class="back" href="{{ route('campaigns.index') }}">← Campaigns</a>
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Campaign</span>@if($c->brand)<span>{{ $c->brand->name }}</span>@endif<span>Owner: {{ $c->owner?->name ?? 'nobody yet' }}</span><span>Tag: {{ $c->tag }}</span></div>
            <h1 class="form-h">{{ $c->name }}</h1>
            @if($c->goal)<p class="sub">{{ $c->goal }}</p>@endif
        </div>
        <a class="btn" href="{{ route('campaigns.edit', $c) }}">Edit</a>
    </div>

    <section class="tiles sales-tiles" aria-label="Where it stands">
        <div class="tile"><span class="tl">Dates</span><span class="tv or-tv">{{ $c->dateLabel() }}</span>
            <span class="sub">{{ match ($c->state()) { 'upcoming' => 'Starts in '.$today->diffInDays($c->starts_on).' '.\Illuminate\Support\Str::plural('day', (int) $today->diffInDays($c->starts_on)), 'running' => ($d = (int) $today->diffInDays($c->ends_on)) === 0 ? 'Last day today' : $d.' '.\Illuminate\Support\Str::plural('day', $d).' left', default => 'Ended' } }}</span></div>
        <div class="tile"><span class="tl">Tasks</span><span class="tv or-tv">{{ $done->count() }} of {{ $open->count() + $done->count() }}</span><span class="sub">{{ $open->filter(fn ($t) => $t->isOverdue())->count() }} overdue</span></div>
        <div class="tile"><span class="tl">Tracked links</span><span class="tv or-tv">{{ $links->count() }}</span><span class="sub">Tagged {{ $c->tag }}</span></div>
        <div class="tile"><span class="tl">Results</span><span class="tv or-tv">{{ $results->count() }}</span><span class="sub">Finished tasks with a link</span></div>
    </section>

    <div class="cmp-actions">
        <a class="btn primary" href="{{ route('work.create', ['type' => 'task', 'campaign' => $c->id]) }}">+ Add a task</a>
        <a class="btn" href="{{ route('links.index', array_filter(['campaign' => $c->tag, 'brand_id' => $c->brand_id])) }}#link-form">+ Make a tracked link</a>
        @if($templates->isNotEmpty())
            <details class="cmp-tpl"><summary class="btn">Use a launch template ▾</summary><ul>@foreach($templates as $t)<li><a href="{{ route('templates.run-form', [$t, 'campaign' => $c->id]) }}">{{ $t->name }}</a></li>@endforeach</ul></details>
        @endif
        <a class="btn" href="{{ route('calendar') }}">Action Calendar</a>
    </div>

    <div class="aians-split">
        <section>
            <div class="h2row"><h2>Tasks</h2></div>
            @if($open->isEmpty() && $done->isEmpty())
                <div class="empty">No tasks yet. Add them one at a time, or use a launch template to create the whole checklist.</div>
            @else
                <ul class="review-list cmp-tasks">
                    @foreach($open as $t)
                        <li class="{{ $t->isOverdue() ? 'late' : '' }}"><b>{{ $t->title }}</b> <span class="sub">· {{ $t->assignee?->firstName() }}@if($t->dueLabel()) · {{ $t->dueLabel() }}@endif</span></li>
                    @endforeach
                    @foreach($done as $t)
                        <li class="done"><s>{{ $t->title }}</s> <span class="sub">· ✓ {{ $t->completer?->firstName() }} {{ $t->completed_at->timezone(config('app.timezone'))->format('M j') }}</span>@if($t->outcome || $t->outcome_url)<p class="outcome-v">→ {{ $t->outcome }}@if($t->outcome_url) <a href="{{ $t->outcome_url }}" target="_blank" rel="noopener noreferrer">{{ $t->outcome ? 'Open' : \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $t->outcome_url), 60) }}</a>@endif</p>@endif</li>
                    @endforeach
                </ul>
            @endif
            @if($runs->isNotEmpty())
                <p class="sub">From launch templates: @foreach($runs as $r)<a href="{{ route('templates.show-run', $r) }}">{{ $r->name }}</a>@if(! $loop->last), @endif @endforeach</p>
            @endif
        </section>
        <section>
            <div class="h2row"><h2>Tracked links</h2></div>
            @if($links->isEmpty())
                <div class="empty">No links tagged <code>{{ $c->tag }}</code> yet. Make one for each place the campaign is posted, so its visits and sales show by channel on Sales.</div>
            @else
                <ul class="review-list">
                    @foreach($links as $l)
                        <li><span class="sub">{{ $l->source }} / {{ $l->medium }}@if($l->content) · {{ $l->content }}@endif · {{ $l->user?->firstName() }}</span>
                            <div class="asset-copy"><input type="text" readonly id="cl-{{ $l->id }}" value="{{ $l->url }}" aria-label="Link for {{ $l->source }}"><button type="button" class="btn" data-copy="#cl-{{ $l->id }}">Copy</button></div></li>
                    @endforeach
                </ul>
            @endif
            @if($c->notes)
                <div class="h2row"><h2>Notes</h2></div>
                <div class="card report-box"><p class="or-notes">{{ $c->notes }}</p></div>
            @endif
        </section>
    </div>
</main>
@endsection
