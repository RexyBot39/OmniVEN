@extends('layouts.app', ['title' => 'Content gaps'])
@use('App\Support\Seo\ContentGaps')

@section('content')
<main class="wrap sales cgaps">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Content gaps</span><span>From {{ \App\Support\BrandIcon::html('google', 'xs') }}Search Console and AI answers</span></div>
            <h1 class="form-h">Content gaps @include('partials.star', ['key' => 'page:23', 'label' => 'Content gaps'])</h1>
            <p class="sub">Searches and questions where better content could bring visits. Make one a task, or set it aside.</p>
        </div>
        @if($brands->count() > 1)
            <nav class="sales-brands" aria-label="Brand">
                <a href="{{ route('content-gaps', array_filter(['kind' => $kind])) }}" @if(! $brand) aria-current="true" @endif>All brands</a>
                @foreach($brands as $b)<a href="{{ route('content-gaps', array_filter(['brand' => $b->slug, 'kind' => $kind])) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>@endforeach
            </nav>
        @endif
    </div>

    @if($searchOn && $status['error'])
        <div class="callout warn-callout" role="status"><div><b>The last update from Search Console failed.</b>@can('admin') {{ $status['error'] }}@endcan</div></div>
    @endif

    @if($total)
        <nav class="gap-kinds" aria-label="Kind of gap">
            <a href="{{ route('content-gaps', array_filter(['brand' => $brand?->slug])) }}" @if(! $kind) aria-current="true" @endif>All <span>{{ $total }}</span></a>
            @foreach(ContentGaps::KINDS as $k => $label)
                @if($counts[$k] ?? 0)<a href="{{ route('content-gaps', array_filter(['brand' => $brand?->slug, 'kind' => $k])) }}" @if($kind === $k) aria-current="true" @endif>{{ $label }} <span>{{ $counts[$k] }}</span></a>@endif
            @endforeach
        </nav>
    @endif

    @if($gaps->isEmpty())
        <div class="empty">
            @if($total)
                Nothing here for this filter.
            @elseif(! $searchOn)
                <p><b>No gaps yet.</b> Gaps come from two places: <a href="{{ route('seo') }}">Search</a> (once Google Search Console is connected) and the monthly <a href="{{ route('ai-answers') }}">AI answers</a> check, where a question no assistant mentioned us for becomes a gap.</p>
                @can('admin')<p class="sub">To connect Search Console, follow the steps on the Search page.</p>@endcan
            @else
                <p><b>No open gaps.</b> Everything has a task or was set aside. New ones appear as Search Console and the AI answers check update.</p>
            @endif
        </div>
    @else
        <ol class="gap-list">
            @foreach($gaps as $g)
                <li class="card cgap" style="--bc:{{ $g['brand'] ? 'var(--c'.$g['brand']->colorSlot().')' : 'var(--line)' }}">
                    <div class="gap-main">
                        <div class="gap-top"><span class="gap-kind k-{{ $g['kind'] }}">{{ ContentGaps::KINDS[$g['kind']] }}</span>@if($g['brand'])<span class="bpill" style="--bc:var(--c{{ $g['brand']->colorSlot() }})">{{ $g['brand']->name }}</span>@endif</div>
                        <h2 class="gap-q">“{{ $g['query'] }}”</h2>
                        <p class="gap-why">{{ $g['why'] }} <span class="sub">{{ $g['stats'] }}</span></p>
                        @if($g['page'])<p class="sub gap-page">Page: <a href="{{ $g['page'] }}" target="_blank" rel="noopener noreferrer">{{ parse_url($g['page'], PHP_URL_PATH) ?: $g['page'] }}</a></p>@endif
                    </div>
                    <div class="gap-act">
                        <form method="POST" action="{{ route('content-gaps.task') }}" class="gap-task">
                            @csrf
                            <input type="hidden" name="key" value="{{ $g['key'] }}">
                            <label><span class="sr">Who</span><select name="assignee_id">@foreach($people as $p)<option value="{{ $p->id }}" @selected($p->id === auth()->id())>{{ $p->id === auth()->id() ? 'Me' : $p->name }}</option>@endforeach</select></label>
                            <label><span class="sr">Due</span><input type="date" name="due_on" value="{{ $dueDefault }}"></label>
                            <button class="btn primary" type="submit">Make a task</button>
                        </form>
                        <form method="POST" action="{{ route('content-gaps.dismiss') }}">
                            @csrf
                            <input type="hidden" name="key" value="{{ $g['key'] }}">
                            <button class="mini" type="submit">Set aside</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ol>
        @if($more)<p class="sub">And {{ $more }} more, with fewer searches. Work the top of the list first.</p>@endif
    @endif

    @if($recent->isNotEmpty())
        <section>
            <div class="h2row"><h2>Recently handled</h2></div>
            <ul class="review-list">
                @foreach($recent as $r)
                    <li><b>“{{ $r->query }}”</b>
                        <span class="sub">·
                            @if($r->status === 'tasked')
                                @if($r->task){{ $r->task->isDone() ? 'Done' : 'Task' }} for {{ $r->task->assignee?->firstName() }}@if($r->task->outcome_url) · <a href="{{ $r->task->outcome_url }}" target="_blank" rel="noopener noreferrer">result</a>@endif @else Task deleted @endif
                            @else
                                Set aside by {{ $r->user?->firstName() ?? 'someone' }}
                            @endif
                            · {{ $r->updated_at->format('M j') }}
                        </span>
                        @if($r->status === 'dismissed')
                            <form method="POST" action="{{ route('content-gaps.restore', $r) }}" class="inline-f">@csrf @method('DELETE')<button class="mini" type="submit">Restore</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
@endsection
