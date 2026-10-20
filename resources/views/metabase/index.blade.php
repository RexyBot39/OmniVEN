@extends('layouts.app', ['title' => 'Metabase'])

@section('content')
<main class="wrap sales mb">
    @include('metabase._head', ['title' => $collection['name'] ?? 'Metabase', 'star' => ! $collection && $q === '',
        'lede' => $collection ? null : 'Our Metabase questions and dashboards, drawn in OmniVEN. Open one to see the latest numbers, or write a new query.',
        'open' => \App\Support\Metabase\Metabase::configured() ? \App\Support\Metabase\Metabase::link($collection ? 'collection/'.$collection['id'] : 'collection/root') : null])

    @if(\App\Support\Metabase\Metabase::configured() && ! $error)
        <form method="GET" action="{{ route('metabase') }}" class="seo-search" role="search">
            <label class="sr" for="mb-q">Search Metabase</label>
            <input id="mb-q" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="Search questions and dashboards, e.g. revenue by month" autocomplete="off">
            <button class="btn" type="submit">Search</button>
        </form>

        @if($collection || $q !== '')
            <nav class="mb-crumbs" aria-label="Where you are">
                <a href="{{ route('metabase') }}">Our analytics</a>
                @if($collection)
                    @foreach($collection['path'] as $p)<span aria-hidden="true">›</span><a href="{{ route('metabase', ['c' => $p['id']]) }}">{{ $p['name'] }}</a>@endforeach
                    <span aria-hidden="true">›</span><span>{{ $collection['name'] }}</span>
                @else
                    <span aria-hidden="true">›</span><span>Results for “{{ $q }}”</span>
                @endif
            </nav>
        @endif

        @if(! $items)
            <div class="empty">{{ $q !== '' ? 'Nothing in Metabase matches “'.$q.'”.' : 'This collection is empty, or OmniVEN’s Metabase key can’t see what’s in it.' }}</div>
        @else
            <ul class="mb-items">
                @foreach($items as $i)
                    @php
                        $href = match ($i['model']) {
                            'collection' => route('metabase', ['c' => $i['id']]),
                            'dashboard' => route('metabase.dashboard', $i['id']),
                            default => route('metabase.question', $i['id']),
                        };
                        $kind = ['collection' => 'Collection', 'dashboard' => 'Dashboard'][$i['model']] ?? 'Question';
                    @endphp
                    <li>
                        <a href="{{ $href }}" class="mb-item mb-{{ $i['model'] }}">
                            <span class="mb-ic" aria-hidden="true">
                                @if($i['model'] === 'collection')<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                @elseif($i['model'] === 'dashboard')<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7" height="9" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="15.5" width="7" height="5" rx="1.5"/></svg>
                                @else<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>@endif
                            </span>
                            <span class="mb-item-t"><b>{{ $i['name'] }}</b>@if($i['description'] !== '')<span class="sub">{{ $i['description'] }}</span>@endif</span>
                            <span class="mb-kind sub">{{ $kind }}{{ $i['where'] !== '' && $q !== '' ? ' · '.$i['where'] : '' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="sub sales-note">What appears here is what OmniVEN’s Metabase key is allowed to see. Numbers are fetched fresh each time and aren’t stored in OmniVEN.</p>
    @endif
</main>
@endsection
