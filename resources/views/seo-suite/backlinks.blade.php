@extends('layouts.app', ['title' => 'Backlinks'])

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'Backlinks', 'star' => 37, 'at' => $report['at'] ?? null,
        'lede' => 'Other sites linking to '.($brand?->name ?? 'ours').'. Links from trusted sites are one of the strongest reasons Google ranks a page.'])

    @if($configured && $brand && ! \App\Support\Seo\DataForSeo::backlinks())
        <div class="empty sales-setup">
            <p><b>Backlinks needs DataForSEO’s Backlinks subscription.</b> Only admins see this page until it’s on.</p>
            <ol><li>In the DataForSEO dashboard, open <b>Plans and Subscriptions</b> and turn on <b>Backlinks API</b>.</li><li>In Railway, set <code>DATAFORSEO_BACKLINKS=true</code>.</li></ol>
        </div>
    @elseif($configured && $brand)
        @include('seo-suite._state', ['report' => $report, 'kind' => 'backlinks'])
        @if($d = $report['data'])
            @php
                $s = $d['summary'];
                $metric = request()->query('m') === 'backlinks' ? 'backlinks' : 'domains';
                $points = array_map(fn ($m) => ['label' => \Carbon\CarbonImmutable::parse($m['month'].'-01')->format('M Y'), 'short' => \Carbon\CarbonImmutable::parse($m['month'].'-01')->format('M ’y'), 'v' => $m[$metric], 'c' => null], $d['months']);
            @endphp
            <section class="tiles sales-tiles" aria-label="Summary">
                <div class="tile"><span class="tl">Sites linking to us</span><span class="tv">{{ number_format($s['domains']) }}</span><span class="sub">{{ number_format($s['dofollow_domains']) }} pass ranking strength (dofollow)</span></div>
                <div class="tile"><span class="tl">Links</span><span class="tv">{{ number_format($s['backlinks']) }}</span><span class="sub">all links from other sites</span></div>
                <div class="tile"><span class="tl">Domain strength</span><span class="tv">{{ $s['rank'] }}/100</span><span class="sub">DataForSEO rank, from who links to us</span></div>
                <div class="tile"><span class="tl">Broken links to us</span><span class="tv">{{ number_format($s['broken']) }}</span><span class="sub">point at pages that don’t load: redirect them</span></div>
                <div class="tile"><span class="tl">Spam score</span><span class="tv">{{ $s['spam'] }}</span><span class="sub">0–100, lower is better</span></div>
            </section>

            @if(count($d['months']) > 1)
                <section>
                    <div class="h2row"><h2>History</h2>
                        <nav class="seg" aria-label="Measure">
                            <a class="seg-b" href="{{ route('seo-suite.backlinks', ['brand' => $brand->slug]) }}" aria-pressed="{{ $metric === 'domains' ? 'true' : 'false' }}">Sites linking to us</a>
                            <a class="seg-b" href="{{ route('seo-suite.backlinks', ['brand' => $brand->slug, 'm' => 'backlinks']) }}" aria-pressed="{{ $metric === 'backlinks' ? 'true' : 'false' }}">Links</a>
                        </nav>
                    </div>
                    @include('partials.trend', ['points' => $points, 'fmt' => 'int', 'name' => $metric === 'backlinks' ? 'Links' : 'Sites linking to us', 'cmp' => null, 'grain' => 'month', 'lower' => false])
                </section>
            @endif

            <div class="aians-split">
                <section>
                    <div class="h2row"><h2>Newest links</h2><span class="sub">One per site</span></div>
                    @include('seo-suite._links', ['rows' => $d['new'], 'date' => 'first_seen', 'empty' => 'No new links found.'])
                </section>
                <section>
                    <div class="h2row"><h2>Links we lost</h2><span class="sub">Strongest first: worth asking to restore</span></div>
                    @include('seo-suite._links', ['rows' => $d['lost'], 'date' => 'lost', 'empty' => 'No lost links. 🎉', 'lostTask' => true])
                </section>
            </div>

            <section>
                <div class="h2row"><h2>Strongest sites linking to us</h2></div>
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Site</th><th class="n">Strength</th><th class="n">Links to us</th><th>Linking since</th><th class="n">Spam score</th></tr></thead>
                        <tbody>
                            @foreach($d['domains'] as $r)
                                <tr><td class="seo-key"><a href="https://{{ $r['domain'] }}" target="_blank" rel="noopener noreferrer">{{ $r['domain'] }}</a></td><td class="n">{{ $r['rank'] }}</td><td class="n">{{ number_format($r['backlinks']) }}</td><td>{{ $r['first_seen'] ? \Carbon\CarbonImmutable::parse($r['first_seen'])->format('M Y') : '—' }}</td><td class="n">{{ $r['spam'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="sub sales-note">Win more: pitch the sites in <a href="{{ route('outreach.index') }}">Outreach</a>, and look at who links to the competitors on <a href="{{ route('seo-suite.competitors', ['brand' => $brand->slug]) }}">SEO competitors</a>.</p>
            </section>
        @endif
        @include('seo-suite._spend')
    @endif
</main>
@endsection
