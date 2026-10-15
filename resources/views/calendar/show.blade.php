@extends('layouts.app', ['title' => 'Action calendar · Week '.$week->number])

@php
    use App\Support\BrandIcon;
    $isAdmin = auth()->user()->can('admin');
    $p = $progress[$week->id];
    $pct = $p['total'] ? (int) round($p['done'] / $p['total'] * 100) : 0;
    $spendWeek = $boosts->sum(fn ($b) => $b->spend());
    $boosted = $boosts->where('beat_median', true)->count();
    $currentIdx = $weeks->search(fn ($w) => $w->is($current));
@endphp

@section('content')
<section class="cal-head wrap">
    <div class="cal-title">
        <a href="{{ route('dashboard') }}" class="back">← OmniVEN</a>
        <div class="eyebrow"><span>39DollarGlasses · Ocusafe · Neurolux</span></div>
        <h1>Q4 Action Calendar @include('partials.star', ['key' => 'page:8', 'label' => 'the action calendar'])</h1>
    </div>
    <dl class="cal-metrics">
        <div><dt>This week</dt><dd>W{{ $current->number }}</dd></div>
        <div><dt>Week done</dt><dd>{{ $p['done'] }}<small>/{{ $p['total'] }}</small></dd></div>
        <div><dt>Quarter</dt><dd>{{ $quarterPct }}<small>%</small></dd></div>
        <div><dt>Boost spend</dt><dd>${{ number_format($totalSpend) }}</dd></div>
    </dl>
</section>

<nav class="cal-rail" aria-label="Weeks">
    <div class="wrap cal-rail-in">
        @foreach($weeks as $i => $w)
            @php($wp = $progress[$w->id])
            @php($wpct = $wp['total'] ? (int) round($wp['done'] / $wp['total'] * 100) : 0)
            <a href="{{ route('calendar.week', $w) }}" @class(['wk', 'sel' => $w->is($week), 'now' => $w->is($current), 'done' => $wpct === 100, 'past' => $i < $currentIdx && $wpct < 100]) @if($w->is($week)) aria-current="page" @endif>
                <span class="wn">Week {{ $w->number }}{{ $w->is($current) ? ' · now' : '' }}</span>
                <span class="wd">{{ $w->range_label }}</span>
                <span class="wp"><span class="track"><b style="width:{{ $wpct }}%"></b></span>{{ $wp['done'] }}/{{ $wp['total'] }}</span>
            </a>
        @endforeach
    </div>
</nav>

<main class="wrap cal-main">
    <div class="cal-col">
        <section class="panel" id="week">
            <h2>Week {{ $week->number }}{{ $week->is($current) ? ' — this week' : '' }}<span class="c">{{ $week->range_label }}</span>@if($isAdmin)<a class="btn add-item" href="{{ route('calendar.items.create', $week) }}">+ Add item</a>@endif</h2>
            @if($week->phase_label)
                <div class="banner phase"><div class="bl">{{ $week->phase_label }}</div><p>{{ $week->phase_text }}</p></div>
            @else
                <div class="banner steady"><div class="bl">Steady state</div><p>Standard runbook week. Nothing to switch on or turn off.</p></div>
            @endif
            @if($week->season_text)
                <div class="banner season"><div class="bl">Seasonal angle</div><p>{{ $week->season_text }}</p></div>
            @endif
            <div class="wkprog"><div class="track"><b style="width:{{ $pct }}%"></b></div><span class="lab">{{ $p['done'] }} of {{ $p['total'] }}</span></div>

            @foreach($items->groupBy('day') as $day => $dayItems)
                <div class="dayhead">{{ $dayItems->first()->dayHeading() }}</div>
                @foreach($dayItems as $it)
                    @php($c = $checks[$it->id] ?? null)
                    @php($brand = $it->brand_slug ? ($brands[$it->brand_slug] ?? null) : null)
                    <div class="cal-task {{ $c ? 'checked' : '' }}" id="item-{{ $it->id }}">
                        <form method="POST" action="{{ route('calendar.toggle', [$week, $it]) }}">@csrf
                            <button class="tick" type="submit" aria-pressed="{{ $c ? 'true' : 'false' }}" aria-label="{{ $c ? 'Uncheck' : 'Check off' }}: {{ \Illuminate\Support\Str::limit($it->title, 60) }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                        </form>
                        <div class="body">
                            <div class="t">@foreach(BrandIcon::mentions($it->title) as $slug){{ BrandIcon::html($slug, 'xs') }}@endforeach{{ $it->title }}</div>
                            <div class="meta">
                                @if($brand)<span class="cpill brand" style="--c:var(--c{{ $brand->colorSlot() }})">{{ $brand->name }}</span>@else<span class="cpill all">All brands</span>@endif
                                @if($it->keyword)<span class="cpill kw">{{ $it->keyword }}</span>@endif
                                @if($it->owner === 'Marc')<span class="cpill marc">Marc signs off</span>@else<span class="cpill own">{{ $it->owner }}</span>@endif
                                @if($isAdmin && ! $it->calendar_week_id && $it->day !== 'Setup')<span class="cpill own" title="Repeats every week">↻ Every week</span>@endif
                                @if($c)<span class="ok-t">✓ {{ $c->user?->firstName() ?? 'Done' }}</span>@endif
                            </div>
                            @if($it->done_when)<div class="dw"><b>Done when</b>{{ $it->done_when }}</div>@endif
                            @if($isAdmin && $c)@include('partials.stamps', ['doneAt' => $c->checked_at, 'doneBy' => $c->user])@endif
                        </div>
                        @if($isAdmin)<a class="mini item-edit" href="{{ route('calendar.items.edit', [$week, $it]) }}" title="{{ $it->calendar_week_id ? 'Only this week' : 'Repeats every week' }}">Edit</a>@endif
                    </div>
                @endforeach
            @endforeach
        </section>

        <section class="panel" id="boost-log">
            <h2>Boost log<span class="c">{{ $boosts->isEmpty() ? 'no decisions logged yet' : $boosted.' boosted · $'.number_format($spendWeek).' this week' }}</span></h2>
            @include('partials.form-errors')
            <form method="POST" action="{{ route('calendar.boosts.store', $week) }}" class="blform">@csrf
                <div class="fld"><label for="b-day">Day</label><select id="b-day" name="day">@foreach(\App\Models\BoostLog::DAYS as $d)<option @selected(old('day', 'Wed') === $d)>{{ $d }}</option>@endforeach</select></div>
                <div class="fld"><label for="b-brand">Brand</label><select id="b-brand" name="brand_slug">@foreach(\App\Models\BoostLog::BRANDS as $slug => $label)<option value="{{ $slug }}" @selected(old('brand_slug') === $slug)>{{ $label }}</option>@endforeach</select></div>
                <div class="fld"><label for="b-format">Format</label><input id="b-format" name="format" placeholder="FrameOfTheWeek" maxlength="40" value="{{ old('format') }}"></div>
                <div class="fld"><label for="b-beat">Beat median?</label><select id="b-beat" name="beat"><option value="yes">Yes — boost</option><option value="no" @selected(old('beat') === 'no')>No — skip</option></select></div>
                <div class="fld"><label for="b-amt">Spend $</label><input id="b-amt" name="amount" type="number" min="0" step="25" value="{{ old('amount', 150) }}"></div>
                <button class="btn primary" type="submit">Log decision</button>
            </form>
            @if($boosts->isEmpty())
                <p class="empty">Nothing logged. Every boost decision goes here, including the ones where you decided not to spend. The skips are what make the log worth keeping.</p>
            @else
                <div class="tablewrap flat">
                    <table>
                        <thead><tr><th>Day</th><th>Brand</th><th>Format</th><th>Beat median</th><th class="n">Spend</th><th>Logged by</th><th><span class="sr">Remove</span></th></tr></thead>
                        <tbody>
                            @foreach($boosts as $b)
                                <tr>
                                    <td>{{ $b->day }}</td><td>{{ $b->brandLabel() }}</td><td>{{ $b->format ?: '—' }}</td>
                                    <td>{!! $b->beat_median ? '<span class="ok-t">yes</span>' : '<span class="sub">no — skipped</span>' !!}</td>
                                    <td class="n ts">${{ number_format($b->spend()) }}</td>
                                    <td>{{ $b->user?->firstName() ?? '—' }}</td>
                                    <td>@if($isAdmin || $b->user_id === auth()->id())<form method="POST" action="{{ route('calendar.boosts.destroy', $b) }}" data-confirm>@csrf @method('DELETE')<button class="mini danger-link" type="submit" data-label="Remove">Remove</button></form>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="panel" id="review">
            <h2>Sunday review — the one written decision<span class="c">@if($week->notes_at){{ $week->notesAuthor?->firstName() ?? 'Saved' }}{{ $isAdmin ? ' · '.$week->notes_at->timezone(config('app.timezone'))->format('M j, g:ia') : '' }}@endif</span></h2>
            <form method="POST" action="{{ route('calendar.notes', $week) }}" class="notes-form">@csrf @method('PUT')
                <label for="cal-notes" class="sr">Sunday review note for week {{ $week->number }}</label>
                <textarea id="cal-notes" name="notes" maxlength="5000" placeholder="What changed this week, and what we're changing next week. One decision is enough.">{{ old('notes', $week->notes) }}</textarea>
                <div class="notes-actions"><button class="btn" type="submit">Save note</button></div>
            </form>
        </section>
    </div>

    <aside class="cal-side">
        @include('calendar.reference')
        <section class="panel side">
            <h3>Export</h3>
            <div class="bd exports">
                <a class="btn" href="{{ route('calendar.export.boosts') }}">Boost log CSV</a>
                <a class="btn" href="{{ route('calendar.export.tasks') }}">Task log CSV</a>
            </div>
        </section>
    </aside>
</main>
<footer class="wrap cal-foot sub">Checkmarks, the boost log and notes are shared: everyone sees the same state. FSA note: most flexible spending accounts expire December 31, but some plans allow a grace period into mid-March or a limited carryover, so phrase deadline copy as “most plans” rather than a flat date. October is Eye Injury Prevention and Home Eye Safety Month; November is Diabetic Eye Disease Awareness Month.</footer>
@endsection
