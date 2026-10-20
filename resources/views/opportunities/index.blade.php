@extends('layouts.app', ['title' => 'Ocusafe bids & RFPs'])
@use('App\Models\Opportunity')
@use('App\Http\Controllers\OpportunitiesController')

@section('content')
<main class="wrap sales opps">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Opportunities</span><span>Ocusafe</span><span>From Tango: SAM.gov, state &amp; local, DLA DIBBS</span>@if($status['last'])<span>Searched {{ $status['last']->format('M j, g:i A') }}</span>@endif</div>
            <h1 class="form-h">Bids &amp; RFPs @include('partials.star', ['key' => 'page:41', 'label' => 'Ocusafe bids & RFPs'])</h1>
            <p class="sub">Government buyers looking for safety glasses, searched every day. Pursue the good ones, pass on the rest, and make a task so the bid goes in before the deadline.</p>
        </div>
        @can('admin')
            @if($configured)<form method="POST" action="{{ route('opportunities.refresh') }}">@csrf<button class="btn" type="submit">{{ $status['running'] ? 'Searching…' : 'Search now' }}</button></form>@endif
        @endcan
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect Tango to start finding bids.</b> Until then only admins can see this page.</p>
            <ol>
                <li>Create an account at <b>tango.makegov.com</b> and copy an API key from your account settings.</li>
                <li>In Railway, set <code>TANGO_API_KEY</code>. Optional: <code>TANGO_SLACK_CHANNEL</code> (e.g. <code>#ocusafe-bids</code>) for a daily digest of new good matches, and <code>TANGO_SLED=false</code> or <code>TANGO_DIBBS=false</code> to skip sources your plan doesn’t include.</li>
                <li>For a search every morning, have a Railway cron run <code>php artisan omniven:opportunities-sync</code>. The page also searches by itself when it’s opened (at most every {{ config('tango.refresh_hours') }} hours).</li>
            </ol>
        </div>
    @else
        @if($status['errors'])
            <div class="callout warn-callout" role="status"><div>
                <b>Some sources couldn’t be searched last time:</b> {{ implode(', ', array_map(fn ($k) => Opportunity::SOURCES[$k] ?? ($k === 'dibbs' ? 'DLA DIBBS' : $k), array_keys($status['errors']))) }}. They’ll be tried again on the next search.
                @can('admin')<ul class="opp-errs">@foreach($status['errors'] as $k => $e)<li><b>{{ $k === 'dibbs' ? 'DLA DIBBS' : (Opportunity::SOURCES[$k] ?? $k) }}:</b> {{ $e }}</li>@endforeach</ul>@endcan
            </div></div>
        @endif
        @if(! $status['last'])
            <div class="empty">The first search is running (it looks back {{ config('tango.lookback_days') }} days). Refresh in a minute or two.</div>
        @endif

        <section class="tiles sales-tiles" aria-label="Summary">
            <div class="tile"><span class="tl">Good matches open</span><span class="tv">{{ number_format($counts['good']) }}</span><span class="sub">likely for safety glasses</span></div>
            <div class="tile"><span class="tl">New this week</span><span class="tv">{{ number_format($counts['new_week']) }}</span><span class="sub">good matches found</span></div>
            <div class="tile"><span class="tl">Closing within 7 days</span><span class="tv @if($counts['closing']) opp-soon @endif">{{ number_format($counts['closing']) }}</span><span class="sub">decide soon</span></div>
            <div class="tile"><span class="tl">Pursuing</span><span class="tv">{{ number_format($counts['pursuing']) }}</span><span class="sub">being prepared or submitted</span></div>
            <div class="tile"><span class="tl">Won</span><span class="tv">{{ number_format($counts['won']) }}</span><span class="sub">so far</span></div>
        </section>

        <nav class="seo-tabs" aria-label="Which bids">
            @foreach(OpportunitiesController::VIEWS as $k => $label)
                <a href="{{ route('opportunities', array_filter(['view' => $k === 'good' ? null : $k, 'source' => $source, 'q' => $q ?: null])) }}" @if($view === $k) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('opportunities') }}" class="seo-search opp-filter" role="search">
            @if($view !== 'good')<input type="hidden" name="view" value="{{ $view }}">@endif
            <label class="sr" for="opp-q">Search bids</label>
            <input id="opp-q" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="Search title, agency or solicitation number" autocomplete="off">
            <label class="sr" for="opp-src">Source</label>
            <select id="opp-src" name="source"><option value="">All sources</option>@foreach(Opportunity::SOURCES as $k => $label)<option value="{{ $k }}" @selected($source === $k)>{{ $label }}</option>@endforeach</select>
            <button class="btn" type="submit">Filter</button>
        </form>

        @if($list->isEmpty())
            <div class="empty">{{ $view === 'good' ? 'No good matches open right now. “All open” shows weaker matches too.' : 'Nothing here.' }}</div>
        @else
            <ul class="opp-list">
                @foreach($list as $o)
                    @php
                        $left = $o->daysLeft();
                        $strength = $o->score >= config('tango.good_score') + 3 ? ['Strong match', 'opp-strong'] : ($o->score >= config('tango.good_score') ? ['Good match', 'opp-good'] : ['Weak match', 'opp-weak']);
                    @endphp
                    <li class="opp @if($focus === $o->id) is-focus @endif opp-st-{{ $o->status }}" id="opp-{{ $o->id }}">
                        <div class="opp-top">
                            <span class="opp-src">{{ Opportunity::SOURCES[$o->source] ?? $o->source }}{{ $o->kind ? ' · '.$o->kind : '' }}</span>
                            <span class="opp-score {{ $strength[1] }}">{{ $strength[0] }}</span>
                            @if($o->status !== 'new')<span class="opp-status">{{ Opportunity::STATUSES[$o->status] }}</span>@endif
                            @if($o->deadline_at)
                                <span @class(['opp-due', 'opp-soon' => $left !== null && $left <= 3, 'opp-gone' => ! $o->isOpen()])>{{ $o->isOpen() ? 'Due '.$o->deadline_at->format('M j, g:i A').' · '.($left === 0 ? 'today' : $left.' '.($left === 1 ? 'day' : 'days').' left') : 'Closed '.$o->deadline_at->format('M j') }}</span>
                            @endif
                        </div>
                        <h2 class="opp-t">@if($o->url)<a href="{{ $o->url }}" target="_blank" rel="noopener noreferrer">{{ $o->title }}</a>@else{{ $o->title }}@endif</h2>
                        <p class="opp-m">
                            @if($o->agency)<span>{{ $o->agency }}</span>@endif
                            @if($o->solicitation_number)<span>#{{ $o->solicitation_number }}</span>@endif
                            @if($o->set_aside)<span>{{ $o->set_aside }}</span>@endif
                            @if($o->place)<span>{{ $o->place }}</span>@endif
                            @if($o->codes)<span>{{ $o->codes }}</span>@endif
                            @if($o->posted_at)<span>Posted {{ $o->posted_at->format('M j') }}</span>@endif
                        </p>
                        @if($o->summary)<details class="opp-sum"><summary class="mini">Description</summary><p>{{ $o->summary }}@if(mb_strlen($o->summary) >= 1000)…@endif</p></details>@endif
                        <div class="opp-acts">
                            @if($o->status === 'new')
                                <form method="POST" action="{{ route('opportunities.update', $o) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="pursuing"><button class="btn primary sm" type="submit">Pursue</button></form>
                                <form method="POST" action="{{ route('opportunities.update', $o) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="passed"><button class="btn sm" type="submit">Pass</button></form>
                            @endif
                            @if($o->task && ! $o->task->isDone())
                                <span class="sub">Task: {{ \Illuminate\Support\Str::limit($o->task->title, 60) }} · {{ $o->task->assignee?->firstName() }}</span>
                            @elseif($o->status !== 'passed' && $o->isOpen())
                                <form method="POST" action="{{ route('opportunities.task', $o) }}">@csrf<button class="btn sm" type="submit">Make a task</button></form>
                            @endif
                            <details class="opp-more">
                                <summary class="mini">{{ $o->owner ? 'Owner: '.$o->owner->firstName() : 'Status & owner' }}</summary>
                                <form method="POST" action="{{ route('opportunities.update', $o) }}" class="opp-more-f">@csrf @method('PATCH')
                                    <label>Status <select name="status">@foreach(Opportunity::STATUSES as $k => $label)<option value="{{ $k }}" @selected($o->status === $k)>{{ $label }}</option>@endforeach</select></label>
                                    <label>Owner <select name="owner_id"><option value="">Nobody yet</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected($o->owner_id === $p->id)>{{ $p->name }}</option>@endforeach</select></label>
                                    <button class="btn sm" type="submit">Save</button>
                                </form>
                            </details>
                            @if($o->matched)<span class="sub opp-why" title="Searches that found it">Found by: {{ $o->matched }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if($list->hasPages())
                <nav class="opp-pages" aria-label="Pages">
                    @if($list->previousPageUrl())<a class="btn" href="{{ $list->previousPageUrl() }}">← Previous</a>@endif
                    <span class="sub">Page {{ $list->currentPage() }} of {{ $list->lastPage() }} · {{ number_format($list->total()) }} bids</span>
                    @if($list->nextPageUrl())<a class="btn" href="{{ $list->nextPageUrl() }}">Next →</a>@endif
                </nav>
            @endif
        @endif

        @can('admin')
            <details class="opp-terms">
                <summary class="btn">Search phrases ({{ $terms->count() }})</summary>
                <div class="opp-terms-b">
                    <p class="sub">Each phrase is searched every day on SAM.gov, state and local portals and DIBBS, along with federal codes PSC {{ implode(', ', config('tango.psc')) }} and NAICS {{ implode(', ', config('tango.naics')) }}. Matches are scored by words like “safety glasses”, “Z87” and “eyewear” in the title; words like “night vision” lower the score.</p>
                    <ul class="opp-term-list">
                        @foreach($terms as $t)
                            <li><span>{{ $t->term }}</span><form method="POST" action="{{ route('opportunities.terms.destroy', $t) }}" data-confirm>@csrf @method('DELETE')<button class="mini danger-link" type="submit" data-label="Remove">Remove</button></form></li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('opportunities.terms.store') }}" class="seo-search">@csrf
                        <label class="sr" for="opp-term">New search phrase</label>
                        <input id="opp-term" name="term" type="text" minlength="3" maxlength="120" placeholder="e.g. anti-fog goggles" required>
                        <button class="btn" type="submit">Add phrase</button>
                    </form>
                </div>
            </details>
        @endcan
    @endif
</main>
@endsection
