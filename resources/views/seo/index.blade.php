@extends('layouts.app', ['title' => 'Search'])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Search</span><span>From {{ \App\Support\BrandIcon::html('google', 'xs') }}Google Search Console</span>@if($s)<span>{{ \Carbon\Carbon::parse($s['from'])->format('M j') }} – {{ \Carbon\Carbon::parse($s['to'])->format('M j') }} vs the 28 days before</span>@endif</div>
            <h1 class="form-h">Search @include('partials.star', ['key' => 'page:20', 'label' => 'Search'])</h1>
            <p class="sub"><a href="{{ route('content-gaps', array_filter(['brand' => $brand?->slug])) }}">Content gaps →</a> searches that need a page, a better page or a better title.</p>
        </div>
        @if($brands->count() > 1)
            <nav class="sales-brands" aria-label="Brand">
                @foreach($brands as $b)<a href="{{ route('seo', ['brand' => $b->slug]) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>@endforeach
            </nav>
        @endif
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect Google Search Console to see search results by brand.</b></p>
            @can('admin')
                <ol>
                    <li>Use the same Google service account as Sales (<code>GA4_CREDENTIALS</code>) and turn on the <b>Google Search Console API</b> in its Google Cloud project.</li>
                    <li>In Search Console, open each brand’s property → Settings → Users and permissions → add the service account’s email as a <b>Restricted</b> user.</li>
                    <li>In Railway, set <code>SEARCH_CONSOLE_SITES</code>, e.g. <code>39dg=sc-domain:39dollarglasses.com,ocusafe=sc-domain:ocusafe.com</code> (use the full https:// address for URL-prefix properties).</li>
                </ol>
            @else
                <p class="sub">Ask an admin to connect it.</p>
            @endcan
        </div>
    @elseif(! $s)
        <div class="empty">{{ $status['error'] ? 'Can’t read Search Console yet.' : 'Loading from Search Console… refresh in a minute.' }}@can('admin') {{ $status['error'] }}@endcan</div>
    @else
        @if($status['error'])
            <div class="callout warn-callout" role="status"><div><b>The last update from Search Console failed.</b> Showing numbers from {{ $status['last']?->format('M j, g:i A') }}.@can('admin') {{ $status['error'] }}@endcan</div></div>
        @endif

        @php
            $pct = fn ($a, $b) => $b > 0 ? ($a - $b) / $b * 100 : null;
            $n = $s['now']; $p = $s['before'];
            $brandedShare = $n['clicks'] > 0 ? $s['branded']['now'] / $n['clicks'] : null;
            $maxDay = max(1, collect($s['daily'])->max('clicks') ?? 1);
        @endphp
        <section class="tiles sales-tiles" aria-label="Last 28 days">
            <div class="tile"><span class="tl">Clicks</span><span class="tv">{{ number_format($n['clicks']) }}</span>@include('partials.delta-28', ['v' => $pct($n['clicks'], $p['clicks'])])</div>
            <div class="tile"><span class="tl">Impressions</span><span class="tv">{{ number_format($n['impressions']) }}</span>@include('partials.delta-28', ['v' => $pct($n['impressions'], $p['impressions'])])</div>
            <div class="tile"><span class="tl">Click-through rate</span><span class="tv">{{ Report::pct($n['ctr'], 1) }}</span><span class="sub">Before: {{ Report::pct($p['ctr'], 1) }}</span></div>
            <div class="tile"><span class="tl">Average position</span><span class="tv">{{ $n['position'] ?? '—' }}</span>
                @if($n['position'] !== null && $p['position'] !== null)
                    @php $better = $p['position'] - $n['position']; @endphp
                    <span class="delta {{ $better > 0.1 ? 'up' : ($better < -0.1 ? 'down' : 'flat') }}">{{ $better > 0.1 ? '▲ up' : ($better < -0.1 ? '▼ down' : '■ steady') }} {{ number_format(abs($better), 1) }}<span class="delta-w"> places</span></span>
                @endif
            </div>
            @if($s['branded']['has_terms'])
                <div class="tile"><span class="tl">Branded searches</span><span class="tv">{{ number_format($s['branded']['now']) }}</span>@include('partials.delta-28', ['v' => $pct($s['branded']['now'], $s['branded']['before'])])<span class="sub">{{ Report::pct($brandedShare, 0) }} of clicks · people looking for us by name</span></div>
            @endif
        </section>

        <section>
            <div class="h2row"><h2>Clicks per day</h2><span class="sub">Last 28 days</span></div>
            <div class="card seo-days">
                <svg viewBox="0 0 {{ count($s['daily']) * 14 }} 60" preserveAspectRatio="none" role="img" aria-label="Clicks per day, {{ count($s['daily']) }} days, from {{ number_format(collect($s['daily'])->min('clicks')) }} to {{ number_format($maxDay) }}">
                    @foreach($s['daily'] as $i => $d)
                        <rect x="{{ $i * 14 + 3 }}" y="{{ 60 - max(1, round($d['clicks'] / $maxDay * 58, 1)) }}" width="8" height="{{ max(1, round($d['clicks'] / $maxDay * 58, 1)) }}" rx="2"><title>{{ \Carbon\Carbon::parse($d['day'])->format('D M j') }}: {{ number_format($d['clicks']) }} clicks</title></rect>
                    @endforeach
                </svg>
            </div>
        </section>

        <div class="aians-split">
            @foreach(['queries' => 'Searches', 'pages' => 'Pages'] as $kind => $label)
                <section>
                    <div class="h2row"><h2>{{ $label }} losing clicks</h2><span class="sub">{{ $kind === 'queries' ? 'Not counting searches for our name' : 'Biggest drops first' }}</span></div>
                    @if(empty($s[$kind]['down']))
                        <div class="empty">Nothing lost clicks. 🎉</div>
                    @else
                        <div class="tablewrap">
                            <table class="sales-ch aians-rates seo-t">
                                <thead><tr><th>{{ $kind === 'queries' ? 'Search' : 'Page' }}</th><th class="n">Clicks</th><th class="n">Change</th><th class="n">Position</th><th><span class="sr">Task</span></th></tr></thead>
                                <tbody>
                                    @foreach($s[$kind]['down'] as $r)
                                        @php $shown = $kind === 'pages' ? (parse_url($r['key'], PHP_URL_PATH) ?: $r['key']) : $r['key']; @endphp
                                        <tr>
                                            <td class="seo-key">@if($kind === 'pages')<a href="{{ $r['key'] }}" target="_blank" rel="noopener noreferrer">{{ $shown }}</a>@else{{ $shown }}@endif</td>
                                            <td class="n">{{ number_format($r['now']) }}</td>
                                            <td class="n"><span class="delta down">▼ {{ number_format(abs($r['change'])) }}</span></td>
                                            <td class="n">{{ $r['position'] ?? '—' }}@if($r['position'] && $r['position_before'])<div class="sub">was {{ $r['position_before'] }}</div>@endif</td>
                                            <td><a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit(($kind === 'pages' ? 'Fix falling page ' : 'Win back “').$shown.($kind === 'pages' ? '' : '”').' (−'.number_format(abs($r['change'])).' clicks)', 190), 'details' => ($kind === 'pages' ? $r['key'] : 'Search: '.$r['key'])."\nClicks: ".$r['before'].' → '.$r['now'].' over 28 days; position '.($r['position_before'] ?? '—').' → '.($r['position'] ?? '—').".\nCheck the page still answers the search, update it, and request indexing in Search Console."]) }}">Make a task</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if(! empty($s[$kind]['up']))
                        <details class="seo-up"><summary class="mini">{{ $label }} gaining clicks ({{ count($s[$kind]['up']) }})</summary>
                            <ul>@foreach($s[$kind]['up'] as $r)<li><span class="seo-key">{{ $kind === 'pages' ? (parse_url($r['key'], PHP_URL_PATH) ?: $r['key']) : $r['key'] }}</span> <span class="delta up">▲ {{ number_format($r['change']) }}</span></li>@endforeach</ul>
                        </details>
                    @endif
                </section>
            @endforeach
        </div>

        <section>
            <div class="h2row"><h2>Top searches</h2><span class="sub">Not counting searches for our name</span></div>
            <div class="tablewrap">
                <table class="sales-ch">
                    <thead><tr><th>Search</th><th class="n">Clicks</th><th class="n">Impressions</th><th class="n">Position</th></tr></thead>
                    <tbody>
                        @foreach($s['top_queries'] as $q)
                            <tr><td class="seo-key">{{ $q['key'] }}</td><td class="n">{{ number_format($q['clicks']) }}</td><td class="n">{{ number_format($q['impressions']) }}</td><td class="n">{{ $q['position'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="sub sales-note">Search Console runs about three days behind. Positions are averages: 1 is the top result.</p>
        </section>
    @endif
</main>
@endsection
