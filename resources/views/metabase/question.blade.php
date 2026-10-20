@extends('layouts.app', ['title' => $card['name'] ?? 'Metabase question'])
@use('App\Support\Metabase\Result')

@section('content')
<main class="wrap sales mb">
    @include('metabase._head', ['title' => $card['name'] ?? 'Question', 'lede' => $card['description'] ?? null,
        'open' => $card ? \App\Support\Metabase\Metabase::link('question/'.$card['id']) : null])

    @if($card && $result)
        <nav class="mb-crumbs" aria-label="Where it lives">
            <a href="{{ route('metabase') }}">Our analytics</a>
            @if($card['collection'])<span aria-hidden="true">›</span><a href="{{ route('metabase', ['c' => $card['collection']['id']]) }}">{{ $card['collection']['name'] }}</a>@endif
        </nav>
        <div class="h2row">
            <nav class="seg" aria-label="Show as">
                @foreach(Result::DISPLAYS as $key => $label)
                    @if($key !== 'auto')<a class="seg-b" href="{{ route('metabase.question', ['card' => $card['id'], 'as' => $key]) }}" aria-pressed="{{ $result->display($as) === $key ? 'true' : 'false' }}">{{ $label }}</a>@endif
                @endforeach
            </nav>
            @if($card['sql'] !== null)<a class="btn" href="{{ route('metabase.query', ['from' => $card['id']]) }}">Edit as a new query</a>@endif
        </div>
        <section>
            @include('metabase._result', ['res' => $result, 'as' => $as, 'name' => $card['name']])
        </section>
    @endif
</main>
@endsection
