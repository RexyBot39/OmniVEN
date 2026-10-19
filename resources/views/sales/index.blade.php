@extends('layouts.app', ['title' => 'Sales'])

@use('App\Support\Revenue\Report')
@php
    $q = fn (array $extra = []) => $range->query(array_merge(['brand' => $brand?->slug, 'metric' => $metric !== 'revenue' ? $metric : null], $extra));
    $vsCol = $vsLabel ? 'vs '.$vsLabel : null;
    $maxShare = max(0.0001, collect($channels['rows'])->max('share') ?? 1);
@endphp

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Sales</span><span>From Google Analytics 4</span>@if($status['last'])<span>Updated {{ $status['last']->format('M j, g:i A') }}</span>@endif</div>
            <h1 class="form-h">Revenue by channel @include('partials.star', ['key' => 'page:14', 'label' => 'Sales'])</h1>
            @can('use-assistant')@if(\App\Support\Assistant\Assistant::configured())<p class="sub"><a href="{{ route('assistant', ['focus' => 'sales']) }}">Ask OmniVEN AI about this →</a></p>@endif @endcan
        </div>
        @include('partials.sales-nav')
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect Google Analytics 4 to see sales here.</b></p>
            @can('admin')
                <ol>
                    <li>In Google Cloud, create a service account, turn on the <b>Google Analytics Data API</b>, and download a JSON key.</li>
                    <li>In each brand’s GA4 property: Admin → Property access management → add the service account’s email as a <b>Viewer</b>.</li>
                    <li>In Railway, set <code>GA4_CREDENTIALS</code> to the JSON key and <code>GA4_PROPERTIES</code> to <code>39dg=PROPERTY_ID,ocusafe=PROPERTY_ID,ocusleep=PROPERTY_ID</code>.</li>
                    <li>Optional: run <code>php artisan omniven:revenue-sync --history</code> once to load last year at once. Otherwise it fills in a month at a time.</li>
                </ol>
            @else
                <p class="sub">Ask an admin to connect it.</p>
            @endcan
        </div>
    @else
        @if($status['error'])
            <div class="callout warn-callout" role="status">
                <div><b>The last update from GA4 failed.</b> {{ $status['last'] ? 'Showing numbers from '.$status['last']->format('M j, g:i A').'.' : 'No numbers yet.' }}@can('admin') {{ $status['error'] }}@endcan</div>
            </div>
        @endif

        <div class="sales-filters">
            <nav class="sales-brands" aria-label="Brand">
                <a href="{{ route('sales', $q(['brand' => null])) }}" @if(! $brand) aria-current="true" @endif>All brands</a>
                @foreach($brands as $b)
                    <a href="{{ route('sales', $q(['brand' => $b->slug])) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>
                @endforeach
            </nav>
        </div>
        @include('partials.range-picker', ['range' => $range, 'route' => 'sales', 'keep' => ['brand' => $brand?->slug, 'metric' => $metric !== 'revenue' ? $metric : null]])
        @php
            $cmpRange = $range->comparison();
            $beforeGa4 = $status['history_floor'] && $cmpRange && $cmpRange[0]->format('Y-m-d') < $status['history_floor'];
        @endphp
        @if($loading)
            <div class="callout" role="status"><div><b>Loading these dates from GA4.</b> {{ $cmpRange ? 'The comparison dates weren’t in OmniVEN yet' : 'These dates weren’t in OmniVEN yet' }}, so they’re being fetched now. Refresh in a minute or two.</div></div>
        @elseif($beforeGa4)
            <p class="sub history-note">GA4 has no numbers before {{ \Carbon\Carbon::parse($status['history_floor'])->format('M j, Y') }}, so part of the comparison is empty.</p>
        @elseif($status['history_from'] && $status['history_floor'] && $status['history_from'] > $status['history_floor'])
            <p class="sub history-note">Older history is still loading from GA4: in so far back to {{ \Carbon\Carbon::parse($status['history_from'])->format('M Y') }}, going back to {{ \Carbon\Carbon::parse($status['history_floor'])->format('M Y') }}.</p>
        @endif

        @php $prev = $figures['prev']; @endphp
        <section class="tiles sales-tiles" aria-label="{{ $range->label() }} at a glance">
            <div class="tile"><span class="tl">Revenue</span><span class="tv">{{ Report::money($figures['cents']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $figures['vs']['cents'], 'vs' => $vsLabel])<span class="tile-was">{{ $prev ? Report::money($prev['cents']) : ($loading ? 'Loading…' : 'No data') }} then</span>@endif @include('partials.pace', ['p' => $pace])</div>
            <div class="tile"><span class="tl">Orders</span><span class="tv">{{ number_format($figures['orders']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $figures['vs']['orders'], 'vs' => $vsLabel])<span class="tile-was">{{ $prev ? number_format($prev['orders']) : ($loading ? 'Loading…' : 'No data') }} then</span>@endif</div>
            <div class="tile"><span class="tl">Sessions</span><span class="tv">{{ number_format($figures['sessions']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $figures['vs']['sessions'], 'vs' => $vsLabel])<span class="tile-was">{{ $prev ? number_format($prev['sessions']) : ($loading ? 'Loading…' : 'No data') }} then</span>@endif</div>
            <div class="tile"><span class="tl">Conversion rate</span><span class="tv">{{ Report::pct($figures['conv'], 2) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $figures['vs']['conv'], 'pts' => true, 'vs' => $vsLabel])<span class="tile-was">{{ $prev ? Report::pct($prev['conv'], 2) : ($loading ? 'Loading…' : 'No data') }} then</span>@endif</div>
            <div class="tile"><span class="tl">Average order</span><span class="tv">{{ Report::money($figures['aov_cents'] !== null ? (int) round($figures['aov_cents']) : null, true) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $figures['vs']['aov'], 'vs' => $vsLabel])<span class="tile-was">{{ $prev ? Report::money($prev['aov_cents'] !== null ? (int) round($prev['aov_cents']) : null, true) : ($loading ? 'Loading…' : 'No data') }} then</span>@endif</div>
        </section>

        <section>
            <div class="h2row"><h2>Over time</h2>
                <nav class="seg" aria-label="Measure">
                    @foreach(Report::METRICS as $k => [$label])
                        <a class="seg-b" href="{{ route('sales', $q(['metric' => $k === 'revenue' ? null : $k])) }}" aria-pressed="{{ $metric === $k ? 'true' : 'false' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            @include('partials.trend', ['points' => $trend, 'fmt' => Report::METRICS[$metric][1], 'name' => Report::METRICS[$metric][0].', '.\App\Support\DateRange::span($range->from, $range->to), 'cmp' => $range->compareLabel(), 'grain' => $range->grain()])
        </section>

        <section>
            <div class="h2row"><h2>By channel</h2><span class="sub">Weekly bars: each channel’s 8 weeks to {{ $channels['week_start']->addWeeks(7)->format('M j') }} (Monday to Sunday), scaled to its own best week; the faded bar is the last week.</span></div>
            @if(empty($channels['rows']))
                <div class="empty">No sales recorded for this period yet.</div>
            @else
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Channel</th><th class="n">Revenue</th><th>Share</th>@if($vsCol)<th class="n">{{ $vsCol }}</th>@endif<th class="n">Orders</th><th class="n">Conv.</th><th>Last 8 weeks</th></tr></thead>
                        <tbody>
                            @foreach($channels['rows'] as $r)
                                <tr>
                                    <td><b>{{ $r['label'] }}</b></td>
                                    <td class="n">{{ Report::money($r['cents']) }}</td>
                                    <td><span class="share"><i style="width:{{ round($r['share'] / $maxShare * 100, 1) }}%"></i></span><span class="share-t">{{ Report::pct($r['share'], $r['share'] > 0 && $r['share'] < 0.01 ? 1 : 0) }}</span></td>
                                    @if($vsCol)<td class="n">@include('partials.delta', ['v' => $r['vs'], 'vs' => $vsLabel])</td>@endif
                                    <td class="n">{{ number_format($r['orders']) }}</td>
                                    <td class="n">{{ Report::pct($r['conv'], 2) }}</td>
                                    <td>
                                        <svg class="spark" viewBox="0 0 96 26" width="96" height="26" role="img" aria-label="{{ $r['label'] }} revenue, last 8 weeks: {{ collect($r['weeks'])->map(fn ($c) => Report::money($c))->implode(', ') }}">
                                            @foreach($r['weeks'] as $i => $c)
                                                <rect x="{{ $i * 12 }}" y="{{ 26 - ($c > 0 ? max(2, round($c / max(1, max($r['weeks'])) * 24, 1)) : 0) }}" width="9" height="{{ $c > 0 ? max(2, round($c / max(1, max($r['weeks'])) * 24, 1)) : 0 }}" rx="1.5" class="{{ $i === 7 ? 'now' : '' }}"><title>Week of {{ $channels['week_start']->addWeeks($i)->format('M j') }}{{ $i === 7 ? ' (so far)' : '' }}: {{ Report::money($c) }}</title></rect>
                                            @endforeach
                                            <rect x="0" y="25.5" width="93" height=".5" class="base"/>
                                        </svg>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="sub sales-note">“AI assistants” counts visits from ChatGPT, Perplexity, Gemini, Copilot and Claude. “Affiliate” counts CJ and Awin links. Revenue is what GA4 records, which usually runs a little below the store’s own total. <a href="{{ route('ai-answers') }}">How often AI assistants mention us →</a></p>
            @endif
        </section>

        @if($byBrand->isNotEmpty())
            <section>
                <div class="h2row"><h2>By brand</h2></div>
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Brand</th><th class="n">Revenue</th>@if($vsCol)<th class="n">{{ $vsCol }}</th>@endif @if($isMtd)<th>This month’s goal</th>@endif<th class="n">Orders</th><th class="n">Conv.</th><th class="n">Average order</th></tr></thead>
                        <tbody>
                            @foreach($byBrand as $b)
                                <tr>
                                    <td><a href="{{ route('sales', $q(['brand' => $b['brand']->slug])) }}"><span class="dot" style="background:var(--c{{ $b['brand']->colorSlot() }})"></span>{{ $b['brand']->name }}</a></td>
                                    <td class="n">{{ Report::money($b['cents']) }}</td>
                                    @if($vsCol)<td class="n">@include('partials.delta', ['v' => $b['vs']['cents'], 'vs' => $vsLabel])</td>@endif
                                    @if($isMtd)<td>@if($brandPace[$b['brand']->id] ?? null)@include('partials.pace', ['p' => $brandPace[$b['brand']->id]])@else<span class="sub">No goal</span>@endif</td>@endif
                                    <td class="n">{{ number_format($b['orders']) }}</td>
                                    <td class="n">{{ Report::pct($b['conv'], 2) }}</td>
                                    <td class="n">{{ Report::money($b['aov_cents'] !== null ? (int) round($b['aov_cents']) : null, true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
        @can('admin')
            <details class="card goals-form" @if($goals->isEmpty()) open @endif>
                <summary><b>{{ $goals->isEmpty() ? 'Set this month’s revenue goals' : 'Edit '.now()->format('F').' goals' }}</b> <span class="sub">Admins · per brand, in dollars · blank clears</span></summary>
                <form method="POST" action="{{ route('sales.goals') }}">
                    @csrf
                    <div class="goals-grid">
                        @foreach($brands as $b)
                            <label><span><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</span><input type="number" name="goal[{{ $b->id }}]" min="0" step="100" value="{{ isset($goals[$b->id]) ? (int) round($goals[$b->id] / 100) : '' }}" placeholder="e.g. 250000"></label>
                        @endforeach
                    </div>
                    <button class="btn primary" type="submit">Save goals</button>
                </form>
            </details>
        @endcan

        <section id="review">
            <div class="h2row"><h2>Weekly review</h2><span class="sub">What moved this week, and why. One note per week.</span></div>
            @php $mine = $notes->first(fn ($n) => \Carbon\Carbon::parse($n->week)->isSameDay($week)); @endphp
            <form method="POST" action="{{ route('sales.note') }}" class="card review-form">
                @csrf
                <label for="review-body"><b>Week of {{ $week->format('M j') }}</b>@if($mine) <span class="sub">· last edited by {{ $mine->user?->firstName() ?? 'someone' }}</span>@endif</label>
                <textarea id="review-body" name="body" maxlength="2000" required placeholder="e.g. Organic search up 18% after the progressive-lens guide; Ocusafe behind goal because Meta ads paused Tuesday.">{{ old('body', $mine?->body) }}</textarea>
                <div class="form-actions"><button class="btn primary" type="submit">{{ $mine ? 'Update note' : 'Save note' }}</button></div>
            </form>
            @if($notes->reject(fn ($n) => $mine && $n->is($mine))->isNotEmpty())
                <ul class="review-list">
                    @foreach($notes->reject(fn ($n) => $mine && $n->is($mine)) as $n)
                        <li><div class="sub"><b>Week of {{ \Carbon\Carbon::parse($n->week)->format('M j, Y') }}</b> · {{ $n->user?->firstName() ?? '—' }}</div><p>{{ $n->body }}</p></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</main>
@endsection
