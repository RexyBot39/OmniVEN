@extends('layouts.app', ['title' => 'Affiliate scoreboard'])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Sales</span><span>From CJ and Awin</span>@foreach($status as $s)@if($s['last'])<span>{{ $s['label'] }} updated {{ $s['last']->format('M j, g:i A') }}</span>@endif @endforeach</div>
            <h1 class="form-h">Affiliate scoreboard @include('partials.star', ['key' => 'page:17', 'label' => 'the Affiliate scoreboard'])</h1>
        </div>
        @include('partials.sales-nav')
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect CJ and Awin to see affiliate results here.</b></p>
            @can('admin')
                <ol>
                    <li><b>CJ:</b> at developers.cj.com, create a Personal Access Token. In Railway set <code>CJ_API_TOKEN</code> to it and <code>CJ_ADVERTISER_ID</code> to our CID (top right in CJ Account Manager).</li>
                    <li><b>Awin:</b> in Awin, open your user menu → API credentials and copy the token. In Railway set <code>AWIN_API_TOKEN</code>. Our advertiser ID 95501 is already filled in; set <code>AWIN_REGION</code> if the program isn’t US.</li>
                    <li>Optional: run <code>php artisan omniven:affiliate-sync --history</code> once to load all the history now (otherwise it fills in a few months each hour).</li>
                </ol>
            @else
                <p class="sub">Ask an admin to connect them.</p>
            @endcan
        </div>
    @else
        @foreach($status as $s)
            @if($s['configured'] && $s['error'])
                <div class="callout warn-callout" role="status"><div><b>The last update from {{ $s['label'] }} failed.</b> {{ $s['last'] ? 'Showing numbers from '.$s['last']->format('M j, g:i A').'.' : 'No numbers yet.' }}@can('admin') {{ $s['error'] }}@endcan</div></div>
            @endif
        @endforeach

        @include('partials.range-picker', ['range' => $range, 'route' => 'affiliates', 'presets' => \App\Http\Controllers\AffiliatesController::PRESETS])
        @php
            $span = $m1->eq($m2) ? $m1->format('F Y') : $m1->format('M Y').' – '.$m2->format('M Y');
            $cmpSpan = $cmpMonths ? ($cmpMonths[0]->eq($cmpMonths[1]) ? $cmpMonths[0]->format('F Y') : $cmpMonths[0]->format('M Y').' – '.$cmpMonths[1]->format('M Y')) : null;
            $p = $prevTotal;
        @endphp
        <p class="sub">Affiliate totals are kept by month: showing <b>{{ $span }}</b>@if($cmpSpan) against <b>{{ $cmpSpan }}</b>@endif.</p>

        <section class="tiles sales-tiles" aria-label="{{ $span }} at a glance">
            <div class="tile"><span class="tl">Affiliate sales</span><span class="tv">{{ Report::money($total['sales']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($total['sales'], $p['sales'] ?? null), 'vs' => $vsLabel])<span class="tile-was">{{ $p ? Report::money($p['sales']) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Orders</span><span class="tv">{{ number_format($total['orders']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($total['orders'], $p['orders'] ?? null), 'vs' => $vsLabel])<span class="tile-was">{{ $p ? number_format($p['orders']) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Commission</span><span class="tv">{{ Report::money($total['comm']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($total['comm'], $p['comm'] ?? null), 'vs' => $vsLabel, 'lower' => true])@endif<span class="sub">Cost of sale {{ Report::pct($total['sales'] > 0 ? $total['comm'] / $total['sales'] : null, 1) }}{{ $p && $p['sales'] > 0 ? ' (was '.Report::pct($p['comm'] / $p['sales'], 1).')' : '' }}</span></div>
            <div class="tile"><span class="tl">Pending commission</span><span class="tv">{{ Report::money($total['pending']) }}</span><span class="sub">Not yet approved or paid</span></div>
            <div class="tile"><span class="tl">Publishers with sales</span><span class="tv">{{ $activeNow }}</span>@if($vsLabel)<span class="tile-was">{{ $activePrev ?? '—' }} then</span>@endif</div>
        </section>

        @if(count($chart) > 1)
            <section>
                <div class="h2row"><h2>Sales by month</h2></div>
                @include('partials.trend', ['points' => $chart, 'fmt' => 'money', 'name' => 'Affiliate sales', 'cmp' => $vsLabel ? ucfirst($vsLabel) : null, 'grain' => 'month'])
            </section>
        @endif

        <section>
            <div class="h2row"><h2>By network</h2></div>
            <div class="tablewrap">
                <table class="sales-ch">
                    <thead><tr><th>Network</th><th class="n">Sales</th><th class="n">Orders</th><th class="n">Commission</th><th class="n">Cost of sale</th><th class="n">Pending</th>@if($vsLabel)<th class="n">Sales then</th><th class="n">Change</th>@endif</tr></thead>
                    <tbody>
                        @foreach($networks as $key => $n)
                            <tr>
                                <td>{{ \App\Support\BrandIcon::html($key, 'xs') }}<b>{{ $n['label'] }}</b>@unless($status[$key]['configured'])<span class="sub"> · not connected</span>@endunless</td>
                                <td class="n">{{ Report::money($n['now']?->sales !== null ? (int) $n['now']->sales : null) }}</td>
                                <td class="n">{{ $n['now'] ? number_format($n['now']->orders) : '—' }}</td>
                                <td class="n">{{ Report::money($n['now']?->comm !== null ? (int) $n['now']->comm : null) }}</td>
                                <td class="n">{{ Report::pct($n['now'] && $n['now']->sales > 0 ? $n['now']->comm / $n['now']->sales : null, 1) }}</td>
                                <td class="n">{{ Report::money($n['now']?->pending !== null ? (int) $n['now']->pending : null) }}</td>
                                @if($vsLabel)<td class="n">{{ Report::money($n['prev']?->sales !== null ? (int) $n['prev']->sales : null) }}</td><td class="n">@include('partials.delta', ['v' => \App\Support\DateRange::change($n['now']->sales ?? null, $n['prev']->sales ?? null), 'vs' => $vsLabel])</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <div class="h2row"><h2>Top publishers</h2><span class="sub">{{ $span }}, by sales</span></div>
            @if($publishers->isEmpty())
                <div class="empty">No affiliate sales recorded for {{ $span }} yet.</div>
            @else
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Publisher</th><th>Network</th><th class="n">Sales</th><th class="n">Orders</th><th class="n">Commission</th><th class="n">Cost of sale</th><th class="n">Pending</th></tr></thead>
                        <tbody>
                            @foreach($publishers as $p)
                                <tr>
                                    <td><b>{{ $p->publisher_name ?? 'Publisher '.$p->publisher_id }}</b><div class="sub">ID {{ $p->publisher_id }}</div></td>
                                    <td>{{ \App\Support\Affiliate\Affiliates::NETWORKS[$p->network] ?? $p->network }}</td>
                                    <td class="n">{{ Report::money((int) $p->sale_cents) }}</td>
                                    <td class="n">{{ number_format($p->orders) }}</td>
                                    <td class="n">{{ Report::money((int) $p->commission_cents) }}</td>
                                    <td class="n">{{ Report::pct($p->sale_cents > 0 ? $p->commission_cents / $p->sale_cents : null, 1) }}</td>
                                    <td class="n">{{ Report::money((int) $p->pending_cents) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section>
            <div class="h2row"><h2>By month</h2><span class="sub">Affiliate sales, both networks{{ $trend->count() > 1 && ! $trend->first()['in_range'] ? ' · the 12 months to '.$m2->format('M Y') : '' }}</span></div>
            <div class="tablewrap">
                <table class="sales-ch aff-trend">
                    <thead><tr><th>Month</th><th>Sales</th>@if($vsLabel)<th class="n">vs {{ $vsLabel }}</th>@endif<th class="n">Orders</th><th class="n">Commission</th><th class="n">Cost of sale</th></tr></thead>
                    <tbody>
                        @foreach($trend->reverse() as $t)
                            <tr>
                                <td><a href="{{ route('affiliates', ['range' => 'custom', 'from' => $t['month']->format('Y-m-d'), 'to' => $t['month']->endOfMonth()->format('Y-m-d'), 'compare' => $range->compare]) }}">{{ $t['month']->format('M Y') }}</a></td>
                                <td><span class="share wide"><i style="width:{{ round($t['sales'] / max(1, $trend->max('sales')) * 100, 1) }}%"></i></span><span class="share-t">{{ Report::money($t['sales']) }}</span></td>
                                @if($vsLabel)<td class="n">@include('partials.delta', ['v' => \App\Support\DateRange::change($t['sales'], $t['then_sales']), 'vs' => $vsLabel])</td>@endif
                                <td class="n">{{ number_format($t['orders']) }}</td>
                                <td class="n">{{ Report::money($t['comm']) }}</td>
                                <td class="n">{{ Report::pct($t['sales'] > 0 ? $t['comm'] / $t['sales'] : null, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</main>
@endsection
