@extends('layouts.app', ['title' => 'Marketing report · '.$month->format('F Y')])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales report">
    <div class="act-head no-print">
        <nav class="weeknav" aria-label="Choose month">
            <a class="btn" href="{{ route('reports.monthly', ['month' => $month->subMonth()->format('Y-m')]) }}">← {{ $month->subMonth()->format('F') }}</a>
            @if($partial === false && $month->addMonth()->lte(now()))<a class="btn" href="{{ route('reports.monthly', ['month' => $month->addMonth()->format('Y-m')]) }}">{{ $month->addMonth()->format('F') }} →</a>@endif
            <button type="button" class="btn primary" data-print>Print or save as PDF</button>
        </nav>
        @include('partials.sales-nav')
    </div>

    <header class="report-h">
        <div class="eyebrow"><span>OmniVEN · 39DollarGlasses marketing</span><span>{{ $partial ? 'Month to date, through '.$end->format('M j') : 'Full month' }}</span></div>
        <h1 class="form-h">Marketing report: {{ $month->format('F Y') }} @include('partials.star', ['key' => 'page:22', 'label' => 'the Monthly report'])</h1>
    </header>

    <section class="tiles sales-tiles" aria-label="Headline numbers">
        <div class="tile"><span class="tl">Revenue (GA4)</span><span class="tv">{{ $revenue['has_data'] ? Report::money($revenue['cents']) : '—' }}</span>@include('partials.delta', ['v' => $revenue['vs']['cents']])@if($goalTotal)<span class="sub">{{ round($revenue['cents'] / $goalTotal * 100) }}% of {{ Report::money((int) $goalTotal) }} goal</span>@endif</div>
        <div class="tile"><span class="tl">Orders</span><span class="tv">{{ $revenue['has_data'] ? number_format($revenue['orders']) : '—' }}</span>@include('partials.delta', ['v' => $revenue['vs']['orders']])</div>
        <div class="tile"><span class="tl">Conversion rate</span><span class="tv">{{ Report::pct($revenue['conv'], 2) }}</span>@include('partials.delta', ['v' => $revenue['vs']['conv'], 'pts' => true])</div>
        <div class="tile"><span class="tl">Tasks finished</span><span class="tv">{{ number_format($work['done']) }}</span><span class="sub">{{ number_format($work['routines']) }} routine ticks · {{ number_format($work['created']) }} tasks added</span></div>
    </section>

    @if($byBrand->isNotEmpty() && $revenue['has_data'])
        <section>
            <div class="h2row"><h2>By brand</h2></div>
            <div class="tablewrap"><table class="sales-ch">
                <thead><tr><th>Brand</th><th class="n">Revenue</th><th class="n">vs last year</th><th class="n">Goal</th><th class="n">Orders</th><th class="n">Conv.</th></tr></thead>
                <tbody>@foreach($byBrand as $b)<tr><td><b>{{ $b['brand']->name }}</b></td><td class="n">{{ Report::money($b['cents']) }}</td><td class="n">@include('partials.delta', ['v' => $b['vs']['cents']])</td><td class="n">{{ $b['goal'] ? round($b['cents'] / $b['goal'] * 100).'% of '.Report::money($b['goal']) : '—' }}</td><td class="n">{{ number_format($b['orders']) }}</td><td class="n">{{ Report::pct($b['conv'], 2) }}</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    @if($channels)
        <section>
            <div class="h2row"><h2>Revenue by channel</h2></div>
            <div class="tablewrap"><table class="sales-ch">
                <thead><tr><th>Channel</th><th class="n">Revenue</th><th>Share</th><th class="n">vs last year</th><th class="n">Orders</th></tr></thead>
                <tbody>@foreach($channels as $c)<tr><td><b>{{ $c['label'] }}</b></td><td class="n">{{ Report::money($c['cents']) }}</td><td><span class="share"><i style="width:{{ round($c['share'] * 100, 1) }}%"></i></span><span class="share-t">{{ Report::pct($c['share'], 0) }}</span></td><td class="n">@include('partials.delta', ['v' => $c['vs']])</td><td class="n">{{ number_format($c['orders']) }}</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    <div class="aians-split">
        @if($affiliates['rows'])
            <section>
                <div class="h2row"><h2>Affiliates</h2><span class="sub">CJ and Awin</span></div>
                <div class="card report-box">
                    <p><b>{{ Report::money($affiliates['sales']) }}</b> sales · {{ number_format($affiliates['orders']) }} orders · {{ Report::money($affiliates['comm']) }} commission ({{ Report::pct($affiliates['sales'] ? $affiliates['comm'] / $affiliates['sales'] : null, 1) }} cost of sale)</p>
                    <ol>@foreach($affiliates['top'] as $p)<li>{{ $p->publisher_name ?? 'Publisher '.$p->publisher_id }} <span class="sub">{{ Report::money((int) $p->sale_cents) }}</span></li>@endforeach</ol>
                </div>
            </section>
        @endif
        @if($ratings->isNotEmpty() || $reviews['n'])
            <section>
                <div class="h2row"><h2>Reputation</h2></div>
                <div class="card report-box">
                    @if($reviews['n'])<p><b>{{ number_format($reviews['n']) }}</b> new reviews averaging <b>{{ number_format($reviews['avg'], 1) }}★</b>; {{ $reviews['low'] }} at 2★ or less.</p>@endif
                    <ul>@foreach($ratings as $r)<li>{{ $r['label'] }}: <b>{{ $r['snap']->rating !== null ? number_format((float) $r['snap']->rating, 1).'★' : '—' }}</b>@if($r['snap']->review_count) <span class="sub">({{ number_format($r['snap']->review_count) }} reviews)</span>@endif</li>@endforeach</ul>
                </div>
            </section>
        @endif
        @if($ai['n'])
            <section>
                <div class="h2row"><h2>AI answers</h2></div>
                <div class="card report-box"><p>AI assistants mentioned us in <b>{{ Report::pct($ai['mention'], 0) }}</b> of {{ $ai['n'] }} answers checked, and recommended us in <b>{{ Report::pct($ai['recommend'], 0) }}</b>.</p></div>
            </section>
        @endif
        <section>
            <div class="h2row"><h2>Work done</h2></div>
            <div class="card report-box">
                <p><b>{{ number_format($work['done']) }}</b> tasks finished, <b>{{ number_format($work['routines']) }}</b> routine ticks, <b>{{ number_format($work['links']) }}</b> campaign links made.</p>
                @if($work['byPerson']->isNotEmpty())<ul>@foreach($work['byPerson']->take(6) as $p)<li>{{ $p['user']?->name ?? 'Someone' }}: {{ $p['n'] }} {{ Str::plural('task', $p['n']) }}</li>@endforeach</ul>@endif
                @if($work['byBrand']->isNotEmpty())<p class="sub">By brand: {{ $work['byBrand']->map(fn ($n, $b) => $b.' '.$n)->implode(' · ') }}</p>@endif
            </div>
        </section>
    </div>

    @if($shipped->isNotEmpty())
        <section>
            <div class="h2row"><h2>What we shipped</h2><span class="sub">{{ $shipped->count() }} finished {{ Str::plural('task', $shipped->count()) }} with a result</span></div>
            <ul class="review-list shipped">@foreach($shipped->take(40) as $t)<li><b>{{ $t->title }}</b>@if($t->brand) <span class="sub">· {{ $t->brand->name }}</span>@endif<span class="sub"> · {{ $t->completer?->firstName() }} · {{ $t->completed_at->format('M j') }}</span>
                <p>@if($t->outcome){{ $t->outcome }}@endif @if($t->outcome_url)<a href="{{ $t->outcome_url }}" target="_blank" rel="noopener noreferrer">{{ Str::limit(preg_replace('#^https?://(www\.)?#', '', $t->outcome_url), 70) }}</a>@endif</p></li>@endforeach</ul>
            @if($shipped->count() > 40)<p class="sub">And {{ $shipped->count() - 40 }} more.</p>@endif
        </section>
    @endif

    @if($outreach['pitched'] || $outreach['live']->isNotEmpty())
        <section>
            <div class="h2row"><h2>Outreach</h2><span class="sub">Publishers, review sites and creators</span></div>
            <div class="card report-box">
                <p><b>{{ $outreach['pitched'] }}</b> pitched, <b>{{ $outreach['replied'] }}</b> replied, <b>{{ $outreach['live']->count() }}</b> went live.</p>
                @if($outreach['live']->isNotEmpty())<ul>@foreach($outreach['live'] as $p)<li>{{ $p->name }}@if($p->result_url) · <a href="{{ $p->result_url }}" target="_blank" rel="noopener noreferrer">{{ Str::limit(preg_replace('#^https?://(www\.)?#', '', $p->result_url), 60) }}</a>@endif</li>@endforeach</ul>@endif
            </div>
        </section>
    @endif

    @if($launches->isNotEmpty())
        <section>
            <div class="h2row"><h2>Launches and promotions</h2></div>
            <ul class="review-list">@foreach($launches as $l)<li><b>{{ $l->name }}</b> <span class="sub">· {{ $l->starts_on->format('M j') }} · {{ $l->done_count }} of {{ $l->tasks_count }} tasks done</span></li>@endforeach</ul>
        </section>
    @endif

    @if($notes->isNotEmpty())
        <section>
            <div class="h2row"><h2>What moved, week by week</h2></div>
            <ul class="review-list">@foreach($notes as $n)<li><div class="sub"><b>Week of {{ \Carbon\Carbon::parse($n->week)->format('M j') }}</b> · {{ $n->user?->firstName() }}</div><p>{{ $n->body }}</p></li>@endforeach</ul>
        </section>
    @endif

    <p class="sub sales-note">Revenue is what Google Analytics records and runs a little below the store’s own total. Generated by OmniVEN on {{ now()->format('M j, Y') }}.</p>
</main>
@endsection
