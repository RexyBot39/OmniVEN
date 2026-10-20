@extends('layouts.app', ['title' => 'Rank tracking'])
@use('App\Support\Seo\SeoSuite')

@section('content')
<main class="wrap sales seo-suite">
    @include('seo-suite._head', ['title' => 'Rank tracking', 'star' => 35,
        'lede' => 'Where '.($brand?->name ?? 'each brand').' shows up in Google for the searches that matter to us. Checked every '.config('dataforseo.rank_days').' days (desktop, top '.config('dataforseo.rank_depth').' results).'])

    @if($configured && $brand)
        @if($error)
            <div class="callout warn-callout" role="status"><div><b>The last rank check failed.</b>@can('admin') {{ $error }}@endcan</div></div>
        @endif
        @php
            $checked = $keywords->whereNotNull('checked_at');
            $ranked = $checked->whereNotNull('position');
            $up = $keywords->filter(fn ($k) => ($k->change() ?? 0) > 0)->count();
            $down = $keywords->filter(fn ($k) => ($k->change() ?? 0) < 0)->count();
        @endphp
        <div class="seo-bar">
            <details class="seo-add" @if($keywords->isEmpty()) open @endif>
                <summary class="btn primary">+ Track searches</summary>
                <form method="POST" action="{{ route('seo-suite.rankings.store') }}" class="seo-add-form">@csrf
                    <input type="hidden" name="brand" value="{{ $brand->slug }}">
                    <label for="seo-kws"><b>Searches to track for {{ $brand->name }}</b> <span class="sub">one per line, up to {{ config('dataforseo.max_keywords') }} per brand</span></label>
                    <textarea id="seo-kws" name="keywords" rows="5" required placeholder="prescription safety glasses&#10;blue light glasses&#10;cheap progressive lenses"></textarea>
                    <button class="btn primary" type="submit">Start tracking</button>
                    <p class="sub">Tip: find searches worth tracking on <a href="{{ route('seo-suite.keywords', ['brand' => $brand->slug]) }}">Keyword research</a>, or press Track next to any search in the SEO pages.</p>
                </form>
            </details>
            @if($keywords->isNotEmpty())
                <form method="POST" action="{{ route('seo-suite.rankings.check') }}">@csrf<input type="hidden" name="brand" value="{{ $brand->slug }}"><button class="btn" type="submit">Check now</button></form>
            @endif
            @if($pending)<span class="sub">Checking {{ $pending }} {{ $pending === 1 ? 'search' : 'searches' }}… refresh in a few minutes.</span>@endif
        </div>

        @if($keywords->isEmpty())
            <div class="empty">No searches tracked for {{ $brand->name }} yet. Add the ones customers use to find products like ours.</div>
        @else
            <section class="tiles sales-tiles" aria-label="Summary">
                <div class="tile"><span class="tl">Tracked searches</span><span class="tv">{{ $keywords->count() }}</span><span class="sub">{{ $checked->count() }} checked</span></div>
                <div class="tile"><span class="tl">Average position</span><span class="tv">{{ $ranked->count() ? number_format($ranked->avg('position'), 1) : '—' }}</span><span class="sub">of the {{ $ranked->count() }} where we show up · 1 is the top</span></div>
                <div class="tile"><span class="tl">In the top 3</span><span class="tv">{{ $ranked->where('position', '<=', 3)->count() }}</span><span class="sub">{{ $ranked->where('position', '<=', 10)->count() }} on page one</span></div>
                <div class="tile"><span class="tl">Not in the top {{ config('dataforseo.rank_depth') }}</span><span class="tv">{{ $checked->whereNull('position')->count() }}</span><span class="sub">need a page, or a better one</span></div>
                <div class="tile"><span class="tl">Since the last check</span><span class="tv"><span class="delta up">▲ {{ $up }}</span> <span class="delta down">▼ {{ $down }}</span></span><span class="sub">searches that moved up or down</span></div>
            </section>

            <section>
                <div class="h2row"><h2>Positions</h2>
                    <nav class="seg" aria-label="Sort">
                        @foreach(['position' => 'Best first', 'change' => 'Biggest moves', 'volume' => 'Most searched', 'keyword' => 'A–Z'] as $k => $label)
                            <a class="seg-b" href="{{ route('seo-suite.rankings', ['brand' => $brand->slug, 'sort' => $k === 'position' ? null : $k]) }}" aria-pressed="{{ $sort === $k ? 'true' : 'false' }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>
                <div class="tablewrap">
                    <table class="sales-ch seo-kw">
                        <thead><tr><th>Search</th><th class="n">Position</th><th class="n">Change</th><th class="n">Best</th><th>Last 6 months</th><th class="n">Searches a month</th><th>Difficulty</th><th>Page</th><th><span class="sr">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach($keywords as $k)
                                @php
                                    $ch = $k->change();
                                    [$kdWord, $kdClass] = SeoSuite::difficulty($k->difficulty);
                                @endphp
                                <tr>
                                    <td class="seo-key">{{ $k->keyword }}</td>
                                    <td class="n">@if($k->task_id && ! $k->checked_at)<span class="sub">checking…</span>@elseif(! $k->checked_at)<span class="sub">waiting</span>@else{{ $k->position ?? '—' }}@endif</td>
                                    <td class="n">@if($ch > 0)<span class="delta up">▲ {{ $ch }}</span>@elseif($ch < 0)<span class="delta down">▼ {{ abs($ch) }}</span>@elseif($k->position && ! $k->previous_position && $k->checks->count() > 1)<span class="seo-new">new</span>@else<span class="sub">—</span>@endif</td>
                                    <td class="n">{{ $k->best_position ?? '—' }}</td>
                                    <td>@include('seo-suite._spark', ['values' => $k->checks->pluck('position')->map(fn ($p) => $p ?? (int) config('dataforseo.rank_depth') + 1)->all(), 'lower' => true])</td>
                                    <td class="n">{{ $k->search_volume ? number_format($k->search_volume) : '—' }}</td>
                                    <td><span class="seo-kd {{ $kdClass }}">{{ $k->difficulty ?? '—' }}</span> <span class="sub">{{ $kdWord }}</span></td>
                                    <td class="seo-key">@if($k->url)<a href="{{ $k->url }}" target="_blank" rel="noopener noreferrer">{{ parse_url($k->url, PHP_URL_PATH) ?: $k->url }}</a>@else<span class="sub">—</span>@endif</td>
                                    <td class="seo-acts">
                                        @if(! $k->position || $k->position > 3)
                                            <a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit('SEO: move “'.$k->keyword.'” '.($k->position ? 'up from '.$k->position : 'into the top 10'), 190), 'details' => 'Search: '.$k->keyword."\nPosition: ".($k->position ?? 'not in the top '.config('dataforseo.rank_depth')).($k->url ? "\nOur page: ".$k->url : '')."\nImprove the page (title, headings, answers to what people ask) or write one, then check Rank tracking."]) }}">Make a task</a>
                                        @endif
                                        <form method="POST" action="{{ route('seo-suite.rankings.destroy', $k) }}" data-confirm>@csrf @method('DELETE')<button class="mini danger-link" type="submit" data-label="Stop tracking">Remove</button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="sub sales-note">Positions count Google’s regular results only (not ads, maps or AI answers): 1 is the top. “—” means not in the top {{ config('dataforseo.rank_depth') }}.</p>
            </section>
        @endif
        @include('seo-suite._spend')
    @endif
</main>
@endsection
