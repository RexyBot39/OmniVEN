@extends('layouts.app', ['title' => 'Outreach'])

@section('content')
<main class="wrap sales outreach">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Outreach</span><span>Blogs, review sites, affiliates, creators and press</span></div>
            <h1 class="form-h">Outreach @include('partials.star', ['key' => 'page:26', 'label' => 'Outreach'])</h1>
        </div>
        <a class="btn primary" href="{{ route('outreach.create') }}">+ Add publishers</a>
    </div>

    <form method="GET" action="{{ route('outreach.index') }}" class="or-filters" aria-label="Filter">
        <nav class="gap-kinds" aria-label="Stage">
            <a href="{{ route('outreach.index', array_filter(['who' => $f['who'] === 'mine' ? 'mine' : null, 'type' => $f['type'], 'q' => $f['q']])) }}" @if(! $f['stage']) aria-current="true" @endif>Open <span>{{ collect($counts)->except(config('outreach.closed'))->sum() }}</span></a>
            @foreach($stages as $k => $label)
                @if($counts[$k] ?? 0)<a href="{{ route('outreach.index', array_filter(['stage' => $k, 'who' => $f['who'] === 'mine' ? 'mine' : null, 'type' => $f['type'], 'q' => $f['q']])) }}" @if($f['stage'] === $k) aria-current="true" @endif>{{ $label }} <span>{{ $counts[$k] }}</span></a>@endif
            @endforeach
        </nav>
        <div class="or-f2">
            @if($f['stage'])<input type="hidden" name="stage" value="{{ $f['stage'] }}">@endif
            <select name="who" aria-label="Whose"><option value="all">Everyone’s</option><option value="mine" @selected($f['who'] === 'mine')>Mine</option></select>
            <select name="type" aria-label="Kind"><option value="">All kinds</option>@foreach($types as $k => $label)<option value="{{ $k }}" @selected($f['type'] === $k)>{{ $label }}</option>@endforeach</select>
            <input type="search" name="q" value="{{ $f['q'] }}" placeholder="Name or site" aria-label="Search publishers">
            <button class="btn" type="submit">Show</button>
        </div>
    </form>

    @if($due->isNotEmpty())
        <section>
            <div class="h2row"><h2>Follow up now</h2><span class="sub">{{ $due->count() }} due</span></div>
            <ul class="or-list">@foreach($due as $p)@include('outreach.row', ['p' => $p, 'quick' => true])@endforeach</ul>
        </section>
    @endif

    <section>
        <div class="h2row"><h2>{{ $f['stage'] ? $stages[$f['stage']] : 'In progress' }}</h2></div>
        @php $rest = $f['stage'] ? $list : $list->reject(fn ($p) => $due->contains($p)); @endphp
        @if($rest->isEmpty())
            <div class="empty">{{ $counts->sum() ? 'Nothing here.' : 'No publishers yet. Add the blogs, review sites and creators you want to pitch, or paste a list.' }}</div>
        @else
            <ul class="or-list">@foreach($rest as $p)@include('outreach.row', ['p' => $p, 'quick' => false])@endforeach</ul>
        @endif
    </section>

    @if($live->isNotEmpty())
        <section>
            <div class="h2row"><h2>Recently live</h2><a class="mini" href="{{ route('outreach.index', ['stage' => 'live']) }}">All live →</a></div>
            <ul class="review-list">@foreach($live as $p)<li><a href="{{ route('outreach.show', $p) }}"><b>{{ $p->name }}</b></a> <span class="sub">· {{ $p->live_at?->format('M j') }}</span>@if($p->result_url) · <a href="{{ $p->result_url }}" target="_blank" rel="noopener noreferrer">see it ↗</a>@endif</li>@endforeach</ul>
        </section>
    @endif
</main>
@endsection
