@extends('layouts.app', ['title' => 'Sales'])

@use('App\Support\Revenue\Report')
@php
    $q = fn (array $extra = []) => array_filter(array_merge(['brand' => $brand?->slug, 'period' => $period], $extra), fn ($v) => $v !== null && $v !== '');
    $maxShare = max(0.0001, collect($channels['rows'])->max('share') ?? 1);
@endphp

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Sales</span><span>From Google Analytics 4</span>@if($status['last'])<span>Updated {{ $status['last']->format('M j, g:i A') }}</span>@endif</div>
            <h1 class="form-h">Revenue by channel @include('partials.star', ['key' => 'page:14', 'label' => 'Sales'])</h1>
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

        <form class="sales-filters" method="GET" action="{{ route('sales') }}" aria-label="Choose brand and period">
            <nav class="seg" aria-label="Period">
                @foreach(Report::PERIODS as $key => $label)
                    <a class="seg-b" href="{{ route('sales', $q(['period' => $key])) }}" aria-pressed="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <nav class="sales-brands" aria-label="Brand">
                <a href="{{ route('sales', $q(['brand' => null])) }}" @if(! $brand) aria-current="true" @endif>All brands</a>
                @foreach($brands as $b)
                    <a href="{{ route('sales', $q(['brand' => $b->slug])) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>
                @endforeach
            </nav>
            <span class="sub">{{ $from->format('M j') }} – {{ $to->format('M j, Y') }}, compared with the same dates last year</span>
        </form>

        <section class="tiles sales-tiles" aria-label="{{ Report::PERIODS[$period] }} at a glance">
            <div class="tile"><span class="tl">Revenue</span><span class="tv">{{ Report::money($figures['cents']) }}</span>@include('partials.delta', ['v' => $figures['vs']['cents']])</div>
            <div class="tile"><span class="tl">Orders</span><span class="tv">{{ number_format($figures['orders']) }}</span>@include('partials.delta', ['v' => $figures['vs']['orders']])</div>
            <div class="tile"><span class="tl">Conversion rate</span><span class="tv">{{ Report::pct($figures['conv'], 2) }}</span>@include('partials.delta', ['v' => $figures['vs']['conv'], 'pts' => true])</div>
            <div class="tile"><span class="tl">Average order</span><span class="tv">{{ Report::money($figures['aov_cents'] !== null ? (int) round($figures['aov_cents']) : null, true) }}</span>@include('partials.delta', ['v' => $figures['vs']['aov']])</div>
        </section>

        <section>
            <div class="h2row"><h2>By channel</h2><span class="sub">Weekly bars: each channel’s last 8 weeks (Monday to Sunday), scaled to its own best week; the faded bar is this week so far.</span></div>
            @if(empty($channels['rows']))
                <div class="empty">No sales recorded for this period yet.</div>
            @else
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Channel</th><th class="n">Revenue</th><th>Share</th><th class="n">vs last year</th><th class="n">Orders</th><th class="n">Conv.</th><th>Last 8 weeks</th></tr></thead>
                        <tbody>
                            @foreach($channels['rows'] as $r)
                                <tr>
                                    <td><b>{{ $r['label'] }}</b></td>
                                    <td class="n">{{ Report::money($r['cents']) }}</td>
                                    <td><span class="share"><i style="width:{{ round($r['share'] / $maxShare * 100, 1) }}%"></i></span><span class="share-t">{{ Report::pct($r['share'], $r['share'] > 0 && $r['share'] < 0.01 ? 1 : 0) }}</span></td>
                                    <td class="n">@include('partials.delta', ['v' => $r['vs']])</td>
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
                        <thead><tr><th>Brand</th><th class="n">Revenue</th><th class="n">vs last year</th><th class="n">Orders</th><th class="n">Conv.</th><th class="n">Average order</th></tr></thead>
                        <tbody>
                            @foreach($byBrand as $b)
                                <tr>
                                    <td><a href="{{ route('sales', $q(['brand' => $b['brand']->slug])) }}"><span class="dot" style="background:var(--c{{ $b['brand']->colorSlot() }})"></span>{{ $b['brand']->name }}</a></td>
                                    <td class="n">{{ Report::money($b['cents']) }}</td>
                                    <td class="n">@include('partials.delta', ['v' => $b['vs']['cents']])</td>
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
    @endif
</main>
@endsection
