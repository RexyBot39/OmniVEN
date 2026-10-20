{{--
    Keyword rows (from \App\Support\Seo\SeoSuite::keywordInfo plus extras). $show lists extra columns: position, their, traffic, trend, intent, cpc, url.
    Each row can be tracked for $brand (unless it's in $trackedSet) or turned into a task.
--}}
@php
    $cols = $show ?? [];
    $has = fn ($c) => in_array($c, $cols, true);
@endphp
<div class="tablewrap">
    <table class="sales-ch seo-kw">
        <thead><tr>
            <th>Search</th><th class="n">Searches a month</th><th>Difficulty</th>
            @if($has('position'))<th class="n">Our position</th>@endif
            @if($has('their'))<th class="n">Their position</th>@endif
            @if($has('traffic'))<th class="n">Est. visits</th>@endif
            @if($has('trend'))<th>Last 12 months</th>@endif
            @if($has('intent'))<th>Searcher is</th>@endif
            @if($has('cpc'))<th class="n">Ad cost per click</th>@endif
            @if($has('url'))<th>Page</th>@endif
            <th><span class="sr">Actions</span></th>
        </tr></thead>
        <tbody>
            @foreach($rows as $r)
                @php
                    [$kdWord, $kdClass] = \App\Support\Seo\SeoSuite::difficulty($r['difficulty'] ?? null);
                    $rowPage = $has('their') ? ($r['their_url'] ?? null) : ($r['url'] ?? null);
                    $taskDetails = 'Search: '.$r['keyword']."\nSearches a month: ".($r['volume'] !== null ? number_format($r['volume']) : 'unknown').'; difficulty '.($r['difficulty'] ?? '—').'/100'
                        .(isset($r['position']) ? '; we rank '.$r['position'] : '')
                        .(isset($r['their_position']) ? "\nCompetitor ranks ".$r['their_position'].($rowPage ? ' with '.$rowPage : '') : ($rowPage ? "\nOur page: ".$rowPage : ''))
                        ."\nWrite or improve a page that answers this search better than what ranks now, then add its link as the result.";
                @endphp
                <tr>
                    <td class="seo-key">{{ $r['keyword'] }}</td>
                    <td class="n">{{ $r['volume'] !== null ? number_format($r['volume']) : '—' }}</td>
                    <td><span class="seo-kd {{ $kdClass }}">{{ $r['difficulty'] ?? '—' }}</span> <span class="sub">{{ $kdWord }}</span></td>
                    @if($has('position'))<td class="n">{{ $r['position'] ?? '—' }}</td>@endif
                    @if($has('their'))<td class="n">{{ $r['their_position'] ?? '—' }}</td>@endif
                    @if($has('traffic'))<td class="n">{{ number_format($r['traffic'] ?? 0) }}</td>@endif
                    @if($has('trend'))<td>@include('seo-suite._spark', ['values' => $r['trend'] ?? [], 'lower' => false])</td>@endif
                    @if($has('intent'))<td class="sub">{{ \App\Support\Seo\SeoSuite::intent($r['intent'] ?? null) ?? '—' }}</td>@endif
                    @if($has('cpc'))<td class="n">{{ isset($r['cpc']) ? '$'.number_format($r['cpc'], 2) : '—' }}</td>@endif
                    @if($has('url'))<td class="seo-key">@if($rowPage)<a href="{{ $rowPage }}" target="_blank" rel="noopener noreferrer">{{ parse_url($rowPage, PHP_URL_PATH) ?: $rowPage }}</a>@else — @endif</td>@endif
                    <td class="seo-acts">
                        @if($brand && ! $trackedSet->has(\App\Models\SeoKeyword::normalize($r['keyword'])))
                            <form method="POST" action="{{ route('seo-suite.rankings.store') }}">@csrf<input type="hidden" name="brand" value="{{ $brand->slug }}"><input type="hidden" name="keywords" value="{{ $r['keyword'] }}"><button class="mini" type="submit">Track</button></form>
                        @elseif($brand)
                            <span class="mini">Tracked</span>
                        @endif
                        <a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit('SEO: '.($has('their') ? 'Win “' : 'Rank for “').$r['keyword'].'”', 190), 'details' => $taskDetails]) }}">Make a task</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
