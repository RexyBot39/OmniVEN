@extends('layouts.app', ['title' => 'Keyword research'])
@use('App\Support\Seo\SeoSuite')

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'Keyword research', 'star' => 34,
        'lede' => 'Type a topic or product to see what people search for: how often, how hard it is to reach page one, and whether they’re ready to buy.'])

    @if($configured)
        <form method="GET" action="{{ route('seo-suite.keywords') }}" class="seo-search" role="search">
            @if($brand)<input type="hidden" name="brand" value="{{ $brand->slug }}">@endif
            <label class="sr" for="seo-q">Topic or product</label>
            <input id="seo-q" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="e.g. prescription safety glasses" autocomplete="off" required>
            <div class="seg" role="radiogroup" aria-label="What to find">
                @foreach(SeoSuite::RESEARCH_MODES as $key => [$label, $hint])
                    <label class="seg-b seo-mode" title="{{ $hint }}"><input type="radio" name="mode" value="{{ $key }}" @checked($mode === $key)> {{ $label }}</label>
                @endforeach
            </div>
            <button class="btn primary" type="submit">Find searches</button>
        </form>
        <p class="sub">{{ SeoSuite::RESEARCH_MODES[$mode][1] }}. Google, {{ config('dataforseo.location_code') === 2840 ? 'United States' : 'location '.config('dataforseo.location_code') }}. Results are kept for a week, so repeating a search is free.</p>

        @if($error)
            <div class="callout warn-callout" role="status"><div>{{ $error }}</div></div>
        @elseif($rows !== null && ! $rows)
            <div class="empty">No searches found for “{{ $q }}”. Try fewer or more general words.</div>
        @elseif($rows)
            @php
                $total = array_sum(array_map(fn ($r) => $r['volume'] ?? 0, $rows));
                $easy = count(array_filter($rows, fn ($r) => ($r['difficulty'] ?? 100) < 30 && ($r['volume'] ?? 0) >= 50));
            @endphp
            <section>
                <div class="h2row"><h2>{{ count($rows) }} searches for “{{ $q }}”</h2><span class="sub">{{ number_format($total) }} searches a month in all · {{ $easy }} easy wins (difficulty under 30, 50+ searches)</span></div>
                @include('seo-suite._keyword-table', ['rows' => $rows, 'show' => ['trend', 'intent', 'cpc'], 'trackedSet' => $tracked])
                <p class="sub sales-note"><b>Difficulty</b> is how hard it is to reach Google’s top 10 (0–100). <b>Track</b> adds the search to Rank tracking for {{ $brand?->name ?? 'the brand' }}; <b>Make a task</b> hands it to a writer.</p>
            </section>
        @endif
        @include('seo-suite._spend')
    @endif
</main>
@endsection
