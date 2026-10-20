@extends('layouts.app', ['title' => $dash['name'] ?? 'Metabase dashboard'])

@section('content')
<main class="wrap sales mb">
    @include('metabase._head', ['title' => $dash['name'] ?? 'Dashboard', 'lede' => $dash['description'] ?? null,
        'open' => $dash ? \App\Support\Metabase\Metabase::link('dashboard/'.$dash['id']) : null])

    @if($dash)
        @if($dash['filters'])
            <p class="sub">This dashboard has {{ $dash['filters'] }} {{ $dash['filters'] === 1 ? 'filter' : 'filters' }}; OmniVEN shows it with their default values. Open it in Metabase to change them.</p>
        @endif
        @if(! $dash['cards'])
            <div class="empty">This dashboard has no questions on it.</div>
        @else
            <div class="mb-dash">
                @foreach($dash['cards'] as $c)
                    @if($c['type'] === 'text')
                        <div class="mb-card mb-{{ $c['size'] }} mb-text">{{ \Illuminate\Support\Str::limit(strip_tags($c['text']), 600) }}</div>
                    @else
                        <section class="mb-card mb-{{ $c['size'] }}">
                            <h2><a href="{{ route('metabase.question', $c['id']) }}">{{ $c['name'] }}</a></h2>
                            @include('metabase._result', ['res' => $c['result'], 'as' => $c['display'], 'name' => $c['name']])
                        </section>
                    @endif
                @endforeach
            </div>
        @endif
    @endif
</main>
@endsection
