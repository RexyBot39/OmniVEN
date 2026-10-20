@extends('layouts.app', ['title' => 'Site audit'])
@use('App\Support\Seo\SiteAudit')

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'Site audit', 'star' => 38, 'at' => $audit?->finished_at,
        'lede' => 'DataForSEO crawls '.($brand?->name ?? 'each brand').'’s site (up to '.number_format(config('dataforseo.audit_pages')).' pages) and lists the technical problems that hold pages back in Google. It runs by itself every month.'])

    @if($configured && $brand)
        <div class="seo-bar">
            @if($latest?->status === 'crawling')
                <span class="sub"><b>Crawling now:</b> {{ number_format($latest->pages_crawled) }} of up to {{ number_format($latest->max_pages) }} pages. Refresh in a few minutes.</span>
            @else
                <form method="POST" action="{{ route('seo-suite.audit.start') }}">@csrf<input type="hidden" name="brand" value="{{ $brand->slug }}"><button class="btn primary" type="submit">{{ $audit ? 'Run a new audit' : 'Run the first audit' }}</button></form>
                @if($latest?->status === 'failed')<span class="sub">The last crawl didn’t finish.@can('admin') {{ $latest->error }}@endcan</span>@endif
            @endif
        </div>

        @if(! $audit)
            @if($latest?->status !== 'crawling')<div class="empty">No audit yet for {{ $brand->name }}. Run one: it takes 5–20 minutes and costs a few cents.</div>@endif
        @else
            @php
                $issues = $audit->summary['issues'] ?? [];
                $order = ['error' => 0, 'warning' => 1, 'notice' => 2];
                uksort($issues, fn ($a, $b) => [$order[SiteAudit::ISSUES[$a][1]], -$issues[$a]] <=> [$order[SiteAudit::ISSUES[$b][1]], -$issues[$b]]);
                $bySeverity = collect($issues)->groupBy(fn ($n, $k) => SiteAudit::ISSUES[$k][1], true)->map->sum();
                $pages = collect($audit->pages ?? [])->when($issue, fn ($c) => $c->filter(fn ($p) => in_array($issue, $p['issues'], true)));
            @endphp
            <section class="tiles sales-tiles" aria-label="Summary">
                <div class="tile"><span class="tl">Site health</span><span class="tv">{{ $audit->score !== null ? number_format($audit->score).'/100' : '—' }}</span><span class="sub">DataForSEO’s on-page score</span></div>
                <div class="tile"><span class="tl">Pages crawled</span><span class="tv">{{ number_format($audit->pages_crawled) }}</span><span class="sub">{{ $audit->finished_at?->format('M j') }}@if($audit->starter) · started by {{ $audit->starter->firstName() }}@endif</span></div>
                <div class="tile"><span class="tl">Errors</span><span class="tv seo-err">{{ number_format($bySeverity['error'] ?? 0) }}</span><span class="sub">fix these first</span></div>
                <div class="tile"><span class="tl">Warnings</span><span class="tv seo-warn">{{ number_format($bySeverity['warning'] ?? 0) }}</span><span class="sub">worth fixing</span></div>
                <div class="tile"><span class="tl">Notices</span><span class="tv">{{ number_format($bySeverity['notice'] ?? 0) }}</span><span class="sub">nice to fix</span></div>
            </section>

            <section>
                <div class="h2row"><h2>Problems found</h2><span class="sub">Most serious first. Click one to see the pages.</span></div>
                @if(! $issues)
                    <div class="empty">No problems found. 🎉</div>
                @else
                    <div class="tablewrap">
                        <table class="sales-ch seo-issues">
                            <thead><tr><th>Problem</th><th class="n">Pages</th><th>What to do</th><th><span class="sr">Task</span></th></tr></thead>
                            <tbody>
                                @foreach($issues as $key => $n)
                                    @php [$label, $sev, $fix] = SiteAudit::ISSUES[$key]; @endphp
                                    <tr @if($issue === $key) class="is-on" @endif>
                                        <td><span class="seo-sev sev-{{ $sev }}">{{ ucfirst($sev) }}</span> <a href="{{ route('seo-suite.audit', ['brand' => $brand->slug, 'issue' => $issue === $key ? null : $key]) }}#pages">{{ $label }}</a></td>
                                        <td class="n">{{ number_format($n) }}</td>
                                        <td class="sub">{{ $fix }}</td>
                                        <td><a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit('SEO fix on '.$brand->name.': '.$label.' ('.number_format($n).' pages)', 190), 'details' => $fix."\nSee the pages: ".route('seo-suite.audit', ['brand' => $brand->slug, 'issue' => $key])."\nRun a new site audit when it’s done to confirm."]) }}">Make a task</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section id="pages">
                <div class="h2row"><h2>{{ $issue ? SiteAudit::ISSUES[$issue][0] : 'Pages that need the most work' }}</h2><span class="sub">{{ $issue ? $pages->count().' of the 100 lowest-scoring pages' : 'Lowest score first' }}@if($issue) · <a href="{{ route('seo-suite.audit', ['brand' => $brand->slug]) }}#pages">Show all</a>@endif</span></div>
                @if($pages->isEmpty())
                    <div class="empty">{{ $issue ? 'None of the 100 lowest-scoring pages have this problem; it’s on other pages of the site.' : 'No page list was saved for this audit.' }}</div>
                @else
                    <div class="tablewrap">
                        <table class="sales-ch">
                            <thead><tr><th>Page</th><th class="n">Score</th><th class="n">Status</th><th>Problems</th></tr></thead>
                            <tbody>
                                @foreach($pages->take(100) as $p)
                                    <tr>
                                        <td class="seo-key"><a href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer">{{ parse_url($p['url'], PHP_URL_PATH) ?: $p['url'] }}</a>@if($p['title'] !== '')<div class="sub">{{ $p['title'] }}</div>@endif</td>
                                        <td class="n">{{ $p['score'] ?? '—' }}</td>
                                        <td class="n">{{ $p['status'] ?: '—' }}</td>
                                        <td class="sub">{{ collect($p['issues'])->map(fn ($k) => SiteAudit::ISSUES[$k][0])->implode(' · ') ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="sub sales-note">For structured data (price, stock and stars in Google) and product feeds, see <a href="{{ route('site-check') }}">Site check</a>.</p>
            </section>
        @endif
        @include('seo-suite._spend')
    @endif
</main>
@endsection
