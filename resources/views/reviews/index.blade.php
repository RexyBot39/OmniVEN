@extends('layouts.app', ['title' => 'Reviews'])

@section('content')
<main class="wrap aians">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Reputation</span><span>Shopper Approved and Trustpilot update by themselves</span></div>
            <h1 class="form-h">Reviews @include('partials.star', ['key' => 'page:18', 'label' => 'Reviews'])</h1>
            @if($history)
                <p class="sub rv-history">{{ number_format($history['stored']) }} Shopper Approved reviews stored for analysis{{ $history['oldest'] ? ', back to '.$history['oldest']->format('M Y') : '' }}{{ $history['done'] ? '.' : '. Older ones are still loading (about a thousand an hour).' }}</p>
            @endif
        </div>
        @if($history && $history['stored'] > 0)
            @can('use-assistant')@if(\App\Support\Assistant\Assistant::configured())
                <a class="btn primary" href="{{ route('assistant', ['focus' => 'reputation', 'q' => 'Do a deep analysis of our Shopper Approved reviews from the last year. Start with the best ones (5★): what customers love most, in their own words, and which themes come up most. Then the 1–2★ ones: what goes wrong. Give key learnings and a prioritized list of action items (ad and email copy to use the praise, fixes for the complaints), each as a task title with an owner.']) }}">Analyze with Ask OmniVEN AI</a>
            @endif @endcan
        @endif
    </div>

    <section class="rv-cards" aria-label="Ratings">
        @foreach($cards as $c)
            <article class="tile rv-card">
                <div class="rv-h">
                    <a href="{{ $c['url'] }}" target="_blank" rel="noopener noreferrer"><b>{{ $c['label'] }}</b> ↗</a>
                    <span class="sub">{{ $c['auto'] ? ($c['connected'] ? 'Automatic' : 'Not connected') : 'Updated by hand' }}</span>
                </div>
                <span class="tv">{{ $c['latest']?->rating !== null ? number_format($c['latest']->rating, 1) : '—' }}<small>{{ $c['latest']?->rating !== null ? ' ★' : '' }}</small></span>
                <span class="sub">
                    {{ $c['latest']?->review_count !== null ? number_format($c['latest']->review_count).' reviews' : 'No count yet' }}@if($c['new'] !== null && $c['new'] > 0) · +{{ number_format($c['new']) }} in 30 days @endif
                </span>
                @if($c['change'] !== null)
                    <span class="delta {{ $c['change'] > 0.04 ? 'up' : ($c['change'] < -0.04 ? 'down' : 'flat') }}">{{ $c['change'] > 0.04 ? '▲' : ($c['change'] < -0.04 ? '▼' : '■') }} {{ number_format(abs($c['change']), 2) }}<span class="delta-w"> vs 30 days ago</span></span>
                @endif
                @if($c['latest'])<span class="sub rv-when">As of {{ \Carbon\Carbon::parse($c['latest']->day)->format('M j') }}</span>@endif
                @if($c['status'] && $c['status']['error'])
                    <span class="rv-err">Last update failed.@can('admin') {{ $c['status']['error'] }}@endcan</span>
                @endif
                @unless($c['connected'])
                    <details class="rv-update">
                        <summary class="mini">Update rating</summary>
                        <form method="POST" action="{{ route('reviews.rating') }}">
                            @csrf<input type="hidden" name="platform" value="{{ $c['key'] }}">
                            <label>Stars <input type="number" name="rating" step="0.1" min="0" max="5" required value="{{ $c['latest']?->rating }}"></label>
                            <label>Reviews <input type="number" name="review_count" min="0" value="{{ $c['latest']?->review_count }}"></label>
                            <button class="btn" type="submit">Save</button>
                        </form>
                    </details>
                @endunless
            </article>
        @endforeach
    </section>

    <section>
        <div class="h2row"><h2>Waiting on a reply</h2><span class="sub">Reviews of 2★ or less from the last 30 days whose task isn’t done</span></div>
        @if($needsReply->isEmpty())
            <div class="empty">{{ $anyAuto ? 'Nothing waiting. New low reviews become Hotlist tasks automatically.' : 'Connect Shopper Approved or Trustpilot to catch low reviews automatically.' }}</div>
        @else
            <ul class="rv-list">
                @foreach($needsReply as $r)
                    <li class="rv-item low">
                        <span class="rv-stars" aria-label="{{ $r->stars }} out of 5 stars">{{ str_repeat('★', $r->stars) }}<span class="rv-off">{{ str_repeat('★', 5 - $r->stars) }}</span></span>
                        <div class="rv-body">
                            <div class="rv-meta"><b>{{ $r->platformLabel() }}</b> · {{ $r->reviewed_at->timezone(config('app.timezone'))->format('M j') }}@if($r->task) · <span>Task for {{ $r->task->assignee?->firstName() }}{{ $r->task->due_on ? ', due '.$r->task->due_on->format('M j') : '' }}</span>@endif</div>
                            @if($r->excerpt)<p>“{{ $r->excerpt }}”</p>@endif
                        </div>
                        @if($r->url)<a class="btn" href="{{ $r->url }}" target="_blank" rel="noopener noreferrer">Open review ↗</a>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <div class="h2row">
            <h2>Recent reviews</h2><span class="sub">Last 30 days, Shopper Approved and Trustpilot</span>
            <nav class="sales-brands rv-filter" aria-label="Filter by stars">
                <a href="{{ route('reviews') }}" @if(! $stars) aria-current="true" @endif>All {{ $counts->sum() }}</a>
                <a href="{{ route('reviews', ['stars' => 'low']) }}" @if($stars === 'low') aria-current="true" @endif>2★ or less {{ ($counts[1] ?? 0) + ($counts[2] ?? 0) }}</a>
                @foreach([5, 4, 3] as $s)<a href="{{ route('reviews', ['stars' => $s]) }}" @if($stars === (string) $s) aria-current="true" @endif>{{ $s }}★ {{ $counts[$s] ?? 0 }}</a>@endforeach
            </nav>
        </div>
        @if($recent->isEmpty())
            <div class="empty">No reviews to show{{ $stars ? ' with that rating' : '' }}.</div>
        @else
            <ul class="rv-list">
                @foreach($recent as $r)
                    <li class="rv-item {{ $r->stars <= 2 ? 'low' : '' }}">
                        <span class="rv-stars" aria-label="{{ $r->stars }} out of 5 stars">{{ str_repeat('★', $r->stars) }}<span class="rv-off">{{ str_repeat('★', 5 - $r->stars) }}</span></span>
                        <div class="rv-body">
                            <div class="rv-meta"><b>{{ $r->platformLabel() }}</b> · {{ $r->reviewed_at->timezone(config('app.timezone'))->format('M j') }}</div>
                            @if($r->excerpt)<p>“{{ $r->excerpt }}”</p>@endif
                        </div>
                        @if($r->url)<a class="mini" href="{{ $r->url }}" target="_blank" rel="noopener noreferrer">Open ↗</a>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <div class="h2row"><h2>Ratings over six months</h2><span class="sub">Each platform’s last recorded rating in the month</span></div>
        <div class="tablewrap">
            <table class="sales-ch aians-rates">
                <thead><tr><th>Platform</th>@foreach($months as $m)<th class="n">{{ $m->format('M') }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($trend as $key => $vals)
                        <tr><td><b>{{ config('reviews.platforms.'.$key.'.label') }}</b></td>@foreach($vals as $v)<td class="n">{{ $v !== null ? number_format((float) $v, 1).' ★' : '—' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
