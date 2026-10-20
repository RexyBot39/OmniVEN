@extends('layouts.app', ['title' => 'SEO overview'])
@use('App\Support\Seo\SeoSuite')

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'SEO overview', 'star' => 33, 'at' => $report['at'] ?? null,
        'lede' => 'Where each brand stands in Google: the searches we show up for, the visits they bring, and how links, site health and AI answers are doing.'])

    @if($configured && $brand)
        @include('seo-suite._state', ['report' => $report, 'kind' => 'overview'])
        @php
            $d = $report['data'];
            $now = $d['now'] ?? null;
            $metric = request()->query('m') === 'traffic' ? 'traffic' : 'keywords';
            $months = $d['months'] ?? [];
            $points = array_map(fn ($m) => ['label' => \Carbon\CarbonImmutable::parse($m['month'].'-01')->format('M Y'), 'short' => \Carbon\CarbonImmutable::parse($m['month'].'-01')->format('M ’y'), 'v' => $m[$metric], 'c' => null], $months);
            $checked = $tracked->whereNotNull('position');
            $avg = $checked->count() ? round($checked->avg('position'), 1) : null;
        @endphp
        @if($now)
            <section class="tiles sales-tiles" aria-label="Right now">
                <div class="tile"><span class="tl">Searches we rank for</span><span class="tv">{{ number_format($now['keywords']) }}</span><span class="sub">in Google’s top 100</span></div>
                <div class="tile"><span class="tl">In the top 3</span><span class="tv">{{ number_format($now['top3']) }}</span><span class="sub">{{ number_format($now['top10']) }} on page one</span></div>
                <div class="tile"><span class="tl">Estimated visits a month</span><span class="tv">{{ number_format($now['traffic']) }}</span><span class="sub">from Google, by DataForSEO’s estimate</span></div>
                <div class="tile"><span class="tl">Traffic value</span><span class="tv">${{ number_format($now['value']) }}</span><span class="sub">what those visits would cost in ads</span></div>
                <div class="tile"><span class="tl">Tracked searches</span><span class="tv">{{ $avg ?? '—' }}</span><span class="sub">@if($tracked->count())average position of {{ $tracked->count() }} · <a href="{{ route('seo-suite.rankings', ['brand' => $brand->slug]) }}">Rank tracking →</a>@else<a href="{{ route('seo-suite.rankings', ['brand' => $brand->slug]) }}">Start tracking searches →</a>@endif</span></div>
                <div class="tile"><span class="tl">Site health</span><span class="tv">{{ $audit?->score !== null ? number_format($audit->score).'/100' : '—' }}</span><span class="sub">@if($audit)audit of {{ number_format($audit->pages_crawled) }} pages, {{ $audit->finished_at?->format('M j') }}@else<a href="{{ route('seo-suite.audit', ['brand' => $brand->slug]) }}">Run a site audit →</a>@endif</span></div>
                @if($backlinks)
                    <div class="tile"><span class="tl">Sites linking to us</span><span class="tv">{{ number_format($backlinks['summary']['domains']) }}</span><span class="sub">{{ number_format($backlinks['summary']['backlinks']) }} links · <a href="{{ route('seo-suite.backlinks', ['brand' => $brand->slug]) }}">Backlinks →</a></span></div>
                @endif
                @if($ai)
                    <div class="tile"><span class="tl">AI answer mentions</span><span class="tv">{{ number_format(array_sum(array_column($ai['platforms'], 'mentions'))) }}</span><span class="sub">Google AI and ChatGPT · <a href="{{ route('seo-suite.ai', ['brand' => $brand->slug]) }}">AI visibility →</a></span></div>
                @endif
            </section>

            <section>
                <div class="h2row"><h2>Movement since last month</h2></div>
                <div class="seo-moves">
                    <span class="delta up">▲ {{ number_format($now['up']) }} up</span>
                    <span class="delta down">▼ {{ number_format($now['down']) }} down</span>
                    <span class="seo-new">+ {{ number_format($now['new']) }} new</span>
                    <span class="seo-lost">− {{ number_format($now['lost']) }} dropped out</span>
                    <span class="sub">Searches whose position changed, appeared or disappeared in the top 100.</span>
                </div>
            </section>
        @endif

        @if(count($months) > 1)
            <section>
                <div class="h2row"><h2>History</h2><span class="sub">Every month DataForSEO has, since {{ \Carbon\CarbonImmutable::parse($months[0]['month'].'-01')->format('M Y') }}</span>
                    <nav class="seg" aria-label="Measure">
                        <a class="seg-b" href="{{ route('seo-suite', ['brand' => $brand->slug]) }}" aria-pressed="{{ $metric === 'keywords' ? 'true' : 'false' }}">Searches we rank for</a>
                        <a class="seg-b" href="{{ route('seo-suite', ['brand' => $brand->slug, 'm' => 'traffic']) }}" aria-pressed="{{ $metric === 'traffic' ? 'true' : 'false' }}">Estimated visits</a>
                    </nav>
                </div>
                @include('partials.trend', ['points' => $points, 'fmt' => 'int', 'name' => $metric === 'traffic' ? 'Estimated visits a month' : 'Searches we rank for', 'cmp' => null, 'grain' => 'month', 'lower' => false])
            </section>
        @endif

        @if(! empty($d['keywords']))
            <section>
                <div class="h2row"><h2>Searches bringing the most visits</h2><span class="sub">Top {{ min(25, count($d['keywords'])) }} of {{ number_format($d['total_keywords'] ?? count($d['keywords'])) }}</span></div>
                @include('seo-suite._keyword-table', ['rows' => array_slice($d['keywords'], 0, 25), 'show' => ['position', 'traffic', 'url'], 'trackedSet' => $tracked->pluck('keyword')->flip()])
            </section>
        @endif

        @if(! empty($d['pages']))
            <section>
                <div class="h2row"><h2>Pages bringing the most visits</h2></div>
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Page</th><th class="n">Estimated visits</th><th class="n">Searches it ranks for</th></tr></thead>
                        <tbody>
                            @foreach($d['pages'] as $p)
                                <tr><td class="seo-key"><a href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer">{{ parse_url($p['url'], PHP_URL_PATH) ?: $p['url'] }}</a></td><td class="n">{{ number_format($p['traffic']) }}</td><td class="n">{{ number_format($p['keywords']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <p class="sub sales-note">Visits are DataForSEO’s estimates from search volume and position, best for trends and comparing brands. For actual clicks, see <a href="{{ route('seo', ['brand' => $brand->slug]) }}">Search</a> (Google Search Console).</p>
        @include('seo-suite._spend')
    @endif
</main>
@endsection
