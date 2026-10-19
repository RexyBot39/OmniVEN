@extends('layouts.app', ['title' => 'Search'])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Search</span><span>From {{ \App\Support\BrandIcon::html('google', 'xs') }}Google Search Console</span>@if($status['last'])<span>Updated {{ $status['last']->format('M j, g:i A') }}</span>@endif</div>
            <h1 class="form-h">Search @include('partials.star', ['key' => 'page:20', 'label' => 'Search'])</h1>
            @can('use-assistant')@if(\App\Support\Assistant\Assistant::configured())<p class="sub"><a href="{{ route('assistant', ['focus' => 'search']) }}">Ask OmniVEN AI about this →</a></p>@endif @endcan
            <p class="sub"><a href="{{ route('content-gaps', array_filter(['brand' => $brand?->slug])) }}">Content gaps →</a> searches that need a page, a better page or a better title. <a href="{{ route('site-check') }}">Site check →</a> structured data and product feeds.</p>
        </div>
        @if($brands->count() > 1)
            <nav class="sales-brands" aria-label="Brand">
                @foreach($brands as $b)<a href="{{ route('seo', $range->query(['brand' => $b->slug, 'metric' => $metric !== 'clicks' ? $metric : null])) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>@endforeach
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
    @elseif(! $hasSummary)
        <div class="empty">{{ $status['error'] ? 'Can’t read Search Console yet.' : 'Loading from Search Console… refresh in a minute.' }}@can('admin') {{ $status['error'] }}@endcan</div>
    @else
        @if($status['error'])
            <div class="callout warn-callout" role="status"><div><b>The last update from Search Console failed.</b> Showing numbers from {{ $status['last']?->format('M j, g:i A') }}.@can('admin') {{ $status['error'] }}@endcan</div></div>
        @endif
        @include('partials.range-picker', ['range' => $range, 'route' => 'seo', 'keep' => ['brand' => $brand?->slug, 'metric' => $metric !== 'clicks' ? $metric : null]])

        @php
            // Stored daily totals when there are any; the summary's own totals until history is in.
            $n = $totals ?? $s['now'] ?? null;
            $p = $totals ? $prevTotals : ($s['before'] ?? null);
            $brandedShare = $s && $n && $n['clicks'] > 0 ? $s['branded']['now'] / $n['clicks'] : null;
            $q = fn (array $extra = []) => $range->query(array_merge(['brand' => $brand?->slug, 'metric' => $metric !== 'clicks' ? $metric : null], $extra));
        @endphp
        @if($n)
        <section class="tiles sales-tiles" aria-label="{{ $range->label() }}">
            <div class="tile"><span class="tl">Clicks</span><span class="tv">{{ number_format($n['clicks']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($n['clicks'], $p['clicks'] ?? null), 'vs' => $vsLabel])<span class="tile-was">{{ $p ? number_format($p['clicks']) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Impressions</span><span class="tv">{{ number_format($n['impressions']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($n['impressions'], $p['impressions'] ?? null), 'vs' => $vsLabel])<span class="tile-was">{{ $p ? number_format($p['impressions']) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Click-through rate</span><span class="tv">{{ Report::pct($n['ctr'], 1) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $n['ctr'] !== null && ($p['ctr'] ?? null) !== null ? ($n['ctr'] - $p['ctr']) * 100 : null, 'pts' => true, 'vs' => $vsLabel])<span class="tile-was">{{ $p ? Report::pct($p['ctr'], 1) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Average position</span><span class="tv">{{ $n['position'] ?? '—' }}</span>
                @if($vsLabel && $n['position'] !== null && ($p['position'] ?? null) !== null)
                    @php $better = $p['position'] - $n['position']; @endphp
                    <span class="delta {{ $better > 0.1 ? 'up' : ($better < -0.1 ? 'down' : 'flat') }}">{{ $better > 0.1 ? '▲ up' : ($better < -0.1 ? '▼ down' : '■ steady') }} {{ number_format(abs($better), 1) }}<span class="delta-w"> places vs {{ $vsLabel }}</span></span>
                    <span class="tile-was">{{ $p['position'] }} then · 1 is the top</span>
                @endif
            </div>
            @if($s && $s['branded']['has_terms'])
                <div class="tile"><span class="tl">Branded searches</span><span class="tv">{{ number_format($s['branded']['now']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($s['branded']['now'], $s['branded']['before']), 'vs' => $vsLabel])@endif<span class="sub">{{ Report::pct($brandedShare, 0) }} of clicks · people looking for us by name</span></div>
            @endif
        </section>
        @endif

        <section>
            <div class="h2row"><h2>Over time</h2>
                <nav class="seg" aria-label="Measure">
                    @foreach(\App\Http\Controllers\SeoController::METRICS as $k => [$label])
                        <a class="seg-b" href="{{ route('seo', $q(['metric' => $k === 'clicks' ? null : $k])) }}" aria-pressed="{{ $metric === $k ? 'true' : 'false' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            @if($totals)
                @include('partials.trend', ['points' => $trend, 'fmt' => \App\Http\Controllers\SeoController::METRICS[$metric][1], 'name' => \App\Http\Controllers\SeoController::METRICS[$metric][0].', '.\App\Support\DateRange::span($range->from, $range->to), 'cmp' => $range->compareLabel(), 'grain' => $range->grain(), 'lower' => $metric === 'position'])
                @if($metric === 'position')<p class="sub">Lower is better: position 1 is the top result.</p>@endif
            @else
                <div class="empty">Daily history is loading from Search Console (up to 16 months). Refresh in a minute.</div>
            @endif
        </section>

        @if($rangeError)
            <div class="callout warn-callout" role="status"><div><b>Couldn’t load searches and pages for these dates.</b>@can('admin') {{ $rangeError }}@endcan</div></div>
        @endif
        @if($s)
        @if($s['queries'] !== null)
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
                                            <td><a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit(($kind === 'pages' ? 'Fix falling page ' : 'Win back “').$shown.($kind === 'pages' ? '' : '”').' (−'.number_format(abs($r['change'])).' clicks)', 190), 'details' => ($kind === 'pages' ? $r['key'] : 'Search: '.$r['key'])."\nClicks: ".$r['before'].' → '.$r['now'].' ('.$range->label().' vs '.$vsLabel.'); position '.($r['position_before'] ?? '—').' → '.($r['position'] ?? '—').".\nCheck the page still answers the search, update it, and request indexing in Search Console."]) }}">Make a task</a></td>
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
        @endif

        <section>
            <div class="h2row"><h2>Top searches</h2><span class="sub">{{ \App\Support\DateRange::span(\Carbon\CarbonImmutable::parse($s['from']), \Carbon\CarbonImmutable::parse($s['to'])) }} · not counting searches for our name</span></div>
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
            <p class="sub sales-note">Search Console runs about three days behind and keeps 16 months. Positions are averages: 1 is the top result.</p>
        </section>
        @endif
    @endif
</main>
@endsection
