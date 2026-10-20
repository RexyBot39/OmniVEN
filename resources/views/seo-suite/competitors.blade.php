@extends('layouts.app', ['title' => 'SEO competitors'])

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'SEO competitors', 'star' => 36, 'at' => $report['at'] ?? null,
        'lede' => 'The sites Google puts next to '.($brand?->name ?? 'ours').' most often, and the searches they win that we don’t.'])

    @if($configured && $brand)
        @include('seo-suite._state', ['report' => $report, 'kind' => 'competitors'])
        @if($report['data'])
            <section>
                <div class="h2row"><h2>Who we compete with in Google</h2><span class="sub">Ranked by how many of our searches they also show up for</span></div>
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Site</th><th class="n">Searches we share</th><th class="n">Their average position</th><th class="n">Searches they rank for</th><th class="n">Their est. visits</th><th><span class="sr">Keyword gap</span></th></tr></thead>
                        <tbody>
                            @foreach($report['data'] as $c)
                                <tr @if($vs === $c['domain']) class="is-on" @endif>
                                    <td class="seo-key"><a href="https://{{ $c['domain'] }}" target="_blank" rel="noopener noreferrer">{{ $c['domain'] }}</a></td>
                                    <td class="n">{{ number_format($c['shared']) }}</td>
                                    <td class="n">{{ $c['avg_position'] ?? '—' }}</td>
                                    <td class="n">{{ number_format($c['keywords']) }}</td>
                                    <td class="n">{{ number_format($c['traffic']) }}</td>
                                    <td><a class="mini" href="{{ route('seo-suite.competitors', ['brand' => $brand->slug, 'vs' => $c['domain']]) }}#gap">Keywords they win →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section id="gap">
            <div class="h2row"><h2>Keyword gap</h2><span class="sub">Searches a competitor ranks for and {{ $brand->name }} doesn’t</span></div>
            <form method="GET" action="{{ route('seo-suite.competitors') }}#gap" class="seo-search">
                <input type="hidden" name="brand" value="{{ $brand->slug }}">
                <label class="sr" for="seo-vs">Competitor’s website</label>
                <input id="seo-vs" name="vs" type="text" value="{{ $vs }}" maxlength="120" placeholder="e.g. zennioptical.com" autocomplete="off" required>
                <button class="btn primary" type="submit">Compare</button>
            </form>
            @if($error)
                <div class="callout warn-callout" role="status"><div>{{ $error }}</div></div>
            @elseif($gap !== null && ! $gap)
                <div class="empty">No gap found: {{ $vs }} doesn’t rank for anything {{ $brand->name }} misses, or DataForSEO has no data for it.</div>
            @elseif($gap)
                <p class="sub">{{ count($gap) }} searches {{ $vs }} ranks for and we don’t, most searched first. Easy ones (low difficulty) are the quickest wins.</p>
                @include('seo-suite._keyword-table', ['rows' => $gap, 'show' => ['their', 'intent', 'url'], 'trackedSet' => $tracked])
            @endif
        </section>
        @include('seo-suite._spend')
    @endif
</main>
@endsection
