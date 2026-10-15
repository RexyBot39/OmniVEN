@extends('layouts.app', ['title' => 'Activity'])

@php
    $q = fn (array $extra = []) => array_filter(array_merge(request()->only('person', 'cat', 'visits'), ['week' => $start->format('Y-m-d')], $extra), fn ($v) => $v !== null && $v !== '');
    $level = fn (int $n) => $n === 0 ? 0 : ($n < 3 ? 1 : ($n < 10 ? 2 : 3));
    $inactive = $rows->where('days_active', 0);
@endphp

@section('content')
<main class="wrap act">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Admin activity report</span><span>Visible to Marc and Dr. Marlow only</span></div>
            <h1 class="form-h">Activity @include('partials.star', ['key' => 'page:11', 'label' => 'the Activity report'])</h1>
        </div>
        <div class="act-tools">
            <a class="btn" href="{{ route('activity.export', ['week' => $start->format('Y-m-d')]) }}">Download summary (CSV)</a>
            <a class="btn" href="{{ route('activity.export', ['week' => $start->format('Y-m-d'), 'type' => 'log']) }}">Download full log (CSV)</a>
        </div>
    </div>

    <nav class="weeknav" aria-label="Choose week">
        <a class="btn" href="{{ route('activity.index', $q(['week' => $start->subWeek()->format('Y-m-d'), 'page' => null])) }}">← Previous week</a>
        <strong>Week of {{ $start->format('M j') }} – {{ $end->format($start->month === $end->month ? 'j, Y' : 'M j, Y') }}</strong>
        @unless($isCurrentWeek)
            <a class="btn" href="{{ route('activity.index', $q(['week' => $start->addWeek()->format('Y-m-d'), 'page' => null])) }}">Next week →</a>
            <a class="mini" href="{{ route('activity.index') }}">Back to this week</a>
        @else
            <span class="sub">This week, Mon–Sun (Eastern time)</span>
        @endunless
    </nav>

    <section class="tiles" aria-label="Week at a glance">
        <div class="tile"><span class="tl">People active</span><span class="tv">{{ $totals['active'] }}<small> of {{ $totals['people'] }}</small></span></div>
        <div class="tile"><span class="tl">Tasks completed</span><span class="tv">{{ $totals['tasks_done'] }}</span></div>
        <div class="tile"><span class="tl">Routines ticked</span><span class="tv">{{ $totals['routines'] }}</span></div>
        <div class="tile"><span class="tl">Content added or edited</span><span class="tv">{{ $totals['content'] }}</span></div>
        <div class="tile {{ $totals['overdue'] ? 'warn' : '' }}"><span class="tl">Overdue tasks right now</span><span class="tv">{{ $totals['overdue'] }}</span></div>
    </section>

    @if($inactive->isNotEmpty())
        <div class="callout warn-callout">
            <strong>No activity this week:</strong>
            {{ $inactive->map(fn ($r) => $r['user']->name.($r['last_active'] ? ' (last seen '.$r['last_active']->format('M j').')' : ' (never signed in)'))->implode(', ') }}
        </div>
    @endif

    <section>
        <div class="h2row"><h2>Weekly usage by person</h2><span class="sub">Click a name to see their activity below.</span></div>
        <div class="tablewrap">
            <table class="sum">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th class="days-h">@foreach($days as $d)<span>{{ $d->format('D')[0] }}</span>@endforeach</th>
                        <th class="n">Days active</th>
                        <th class="n">Sign-ins</th>
                        <th class="n">Tasks done</th>
                        <th class="n">Open / overdue</th>
                        <th class="n">Routines</th>
                        <th class="n">Content</th>
                        <th class="n">Sticky notes</th>
                        <th>Last active</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr class="{{ $r['days_active'] === 0 ? 'idle' : '' }}">
                            <td>
                                <a href="{{ route('activity.index', $q(['person' => $r['user']->id, 'page' => null])) }}#feed">{{ $r['user']->name }}</a>
                                @if($r['user']->is_admin)<span class="role">Admin</span>@endif
                                <div class="sub">{{ $r['user']->email }}</div>
                            </td>
                            <td class="days">
                                @foreach($r['by_day'] as $i => $n)
                                    <span class="cell l{{ $level($n) }}" title="{{ $days[$i]->format('D M j') }}: {{ $n }} {{ \Illuminate\Support\Str::plural('action', $n) }}"></span>
                                @endforeach
                            </td>
                            <td class="n"><strong>{{ $r['days_active'] }}</strong>/7</td>
                            <td class="n">{{ $r['sign_ins'] }}</td>
                            <td class="n">{{ $r['tasks_done'] }}</td>
                            <td class="n">{{ $r['open'] }} / <span class="{{ $r['overdue'] ? 'late' : '' }}">{{ $r['overdue'] }}</span></td>
                            <td class="n">{{ $r['routines'] }}</td>
                            <td class="n">{{ $r['content'] }}</td>
                            <td class="n">{{ $r['postit_done'] }}</td>
                            <td class="ts">{{ $r['last_active']?->format('M j, g:i A') ?? 'Never' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="legend sub">
            Day squares: <span class="cell l0"></span> none <span class="cell l1"></span> 1–2 <span class="cell l2"></span> 3–9 <span class="cell l3"></span> 10+ actions.
            Open / overdue shows tasks assigned to them right now. Content = tools, brands, routines and Bulletin notes added or edited. Sticky notes = to-dos checked off (note text stays private).
        </p>
    </section>

    <section id="feed">
        <div class="h2row"><h2>Activity log</h2><span class="sub">{{ $feed->total() }} {{ \Illuminate\Support\Str::plural('entry', $feed->total()) }}, newest first</span></div>
        <form method="GET" action="{{ route('activity.index') }}#feed" class="card feed-filter">
            <input type="hidden" name="week" value="{{ $start->format('Y-m-d') }}">
            <div class="fld"><label for="f-person">Person</label>
                <select id="f-person" name="person">
                    <option value="">Everyone</option>
                    @foreach($people as $p)<option value="{{ $p->id }}" @selected(request('person') == $p->id)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div class="fld"><label for="f-cat">Type</label>
                <select id="f-cat" name="cat">
                    <option value="">All activity</option>
                    @foreach(\App\Models\ActivityLog::CATEGORIES as $k => $c)<option value="{{ $k }}" @selected(request('cat') === $k)>{{ $c['label'] }}</option>@endforeach
                </select>
            </div>
            <label class="inline"><input type="checkbox" name="visits" value="1" @checked(request()->boolean('visits'))> Include page visits</label>
            <button class="btn primary" type="submit">Apply</button>
            @if(request()->hasAny(['person', 'cat', 'visits']))<a class="mini" href="{{ route('activity.index', ['week' => $start->format('Y-m-d')]) }}#feed">Clear</a>@endif
        </form>

        @if($feed->isEmpty())
            <div class="empty">No activity for this week{{ request('person') ? ' for this person' : '' }}.</div>
        @else
            <div class="tablewrap">
                <table class="feed">
                    <thead><tr><th>When (ET)</th><th>Person</th><th>Type</th><th>What happened</th></tr></thead>
                    <tbody>
                        @foreach($feed as $l)
                            <tr>
                                <td class="ts">{{ $l->created_at->setTimezone(config('app.timezone'))->format('D M j, g:i:s A') }}</td>
                                <td>{{ $l->user?->name ?? 'Unknown' }}</td>
                                <td><span class="kind kind-{{ $l->category() }}">{{ \App\Models\ActivityLog::CATEGORIES[$l->category()]['label'] ?? 'Other' }}</span></td>
                                <td>{{ $l->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($feed->hasPages())
                <nav class="pager">
                    @if($feed->previousPageUrl())<a class="btn" href="{{ $feed->previousPageUrl() }}#feed">← Newer</a>@endif
                    <span class="sub">Page {{ $feed->currentPage() }} of {{ $feed->lastPage() }}</span>
                    @if($feed->nextPageUrl())<a class="btn" href="{{ $feed->nextPageUrl() }}#feed">Older →</a>@endif
                </nav>
            @endif
        @endif
    </section>
</main>
@endsection
