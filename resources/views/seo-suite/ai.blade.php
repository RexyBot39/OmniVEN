@extends('layouts.app', ['title' => 'AI visibility'])
@use('App\Support\Seo\SeoSuite')

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'AI visibility', 'star' => 39, 'at' => $report['at'] ?? null,
        'lede' => 'How often Google’s AI answers and ChatGPT mention '.($brand?->name ?? 'us').', which questions bring us up, and how we compare with competitors. Refreshed monthly.'])

    @if($configured && $brand)
        @include('seo-suite._state', ['report' => $report, 'kind' => 'ai'])
        @if($d = $report['data'])
            <section class="tiles sales-tiles" aria-label="Mentions">
                @foreach(SeoSuite::AI_PLATFORMS as $key => $label)
                    @php $p = $d['platforms'][$key] ?? ['mentions' => 0, 'volume' => 0, 'impressions' => 0]; @endphp
                    <div class="tile"><span class="tl">{{ $label }}</span><span class="tv">{{ number_format($p['mentions']) }}</span><span class="sub">{{ $p['volume'] ? 'answers that mention us · asked about '.number_format($p['volume']).' times a month' : 'answers that mention us' }}</span></div>
                @endforeach
            </section>

            @if($d['share'])
                @php $max = max(1, max(array_column($d['share'], 'mentions'))); @endphp
                <section>
                    <div class="h2row"><h2>Share of voice in Google’s AI answers</h2><span class="sub">Mentions of us against our top search competitors</span></div>
                    <ul class="seo-share">
                        @foreach($d['share'] as $s)
                            <li @class(['is-us' => $s['us']])><span class="seo-share-name">{{ $s['domain'] }}@if($s['us']) <b>(us)</b>@endif</span><span class="seo-share-bar"><i style="width:{{ round($s['mentions'] / $max * 100, 1) }}%"></i></span><span class="seo-share-n">{{ number_format($s['mentions']) }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section>
                <div class="h2row"><h2>Questions where Google’s AI mentions us</h2><span class="sub">Most asked first</span></div>
                @if(! $d['questions'])
                    <div class="empty">No AI answers mention {{ $brand->name }} yet. Pages that answer shoppers’ questions plainly (with prices, sizes and comparisons) are what AI answers quote.</div>
                @else
                    <div class="seo-qs">
                        @foreach($d['questions'] as $q)
                            <details class="seo-q">
                                <summary><span>{{ $q['question'] }}</span>@if($q['volume'])<span class="sub">{{ number_format($q['volume']) }} a month</span>@endif</summary>
                                @if($q['answer'] !== '')<p class="seo-ans">{{ $q['answer'] }}@if(mb_strlen($q['answer']) >= 600)…@endif</p>@endif
                                @if($q['sources'])
                                    <p class="sub">Sources it cites:</p>
                                    <ul class="seo-src">@foreach($q['sources'] as $src)<li><a href="{{ $src['url'] }}" target="_blank" rel="noopener noreferrer">{{ $src['domain'] ?: parse_url($src['url'], PHP_URL_HOST) }}</a>@if($src['title'] !== '') <span class="sub">{{ $src['title'] }}</span>@endif</li>@endforeach</ul>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif
                <p class="sub sales-note">Want to check a specific question by hand? <a href="{{ route('ai-answers') }}">AI answers</a> is the monthly manual check across ChatGPT, Perplexity, Gemini and Copilot.</p>
            </section>
        @endif
        @include('seo-suite._spend')
    @endif
</main>
@endsection
