@extends('layouts.app', ['title' => 'Affiliate scoreboard'])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Sales</span><span>From CJ and Awin</span>@foreach($status as $s)@if($s['last'])<span>{{ $s['label'] }} updated {{ $s['last']->format('M j, g:i A') }}</span>@endif @endforeach</div>
            <h1 class="form-h">Affiliate scoreboard @include('partials.star', ['key' => 'page:17', 'label' => 'the Affiliate scoreboard'])</h1>
        </div>
        <nav class="seg" aria-label="Sales pages"><a class="seg-b" href="{{ route('sales') }}" aria-pressed="false">Revenue by channel</a><a class="seg-b" href="{{ route('affiliates') }}" aria-pressed="true">Affiliates</a></nav>
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect CJ and Awin to see affiliate results here.</b></p>
            @can('admin')
                <ol>
                    <li><b>CJ:</b> at developers.cj.com, create a Personal Access Token. In Railway set <code>CJ_API_TOKEN</code> to it and <code>CJ_ADVERTISER_ID</code> to our CID (top right in CJ Account Manager).</li>
                    <li><b>Awin:</b> in Awin, open your user menu → API credentials and copy the token. In Railway set <code>AWIN_API_TOKEN</code>. Our advertiser ID 95501 is already filled in; set <code>AWIN_REGION</code> if the program isn’t US.</li>
                    <li>Optional: run <code>php artisan omniven:affiliate-sync --history</code> once to load the last 13 months now.</li>
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

        <nav class="weeknav" aria-label="Choose month">
            <a class="btn" href="{{ route('affiliates', ['month' => $month->subMonth()->format('Y-m')]) }}">← {{ $month->subMonth()->format('F') }}</a>
            <strong>{{ $month->format('F Y') }}{{ $isCurrent ? ' so far' : '' }}</strong>
            @unless($isCurrent)
                <a class="btn" href="{{ route('affiliates', ['month' => $month->addMonth()->format('Y-m')]) }}">{{ $month->addMonth()->format('F') }} →</a>
                <a class="mini" href="{{ route('affiliates') }}">Back to this month</a>
            @endunless
        </nav>

        <section class="tiles sales-tiles" aria-label="{{ $month->format('F') }} at a glance">
            <div class="tile"><span class="tl">Affiliate sales</span><span class="tv">{{ Report::money($total['sales']) }}</span><span class="sub">{{ $month->subMonth()->format('F') }}: {{ Report::money($prevTotal['sales']) }}</span></div>
            <div class="tile"><span class="tl">Orders</span><span class="tv">{{ number_format($total['orders']) }}</span><span class="sub">{{ $month->subMonth()->format('F') }}: {{ number_format($prevTotal['orders']) }}</span></div>
            <div class="tile"><span class="tl">Commission</span><span class="tv">{{ Report::money($total['comm']) }}</span><span class="sub">Cost of sale {{ Report::pct($total['sales'] > 0 ? $total['comm'] / $total['sales'] : null, 1) }}</span></div>
            <div class="tile"><span class="tl">Pending commission</span><span class="tv">{{ Report::money($total['pending']) }}</span><span class="sub">Not yet approved or paid</span></div>
            <div class="tile"><span class="tl">Publishers with sales</span><span class="tv">{{ $active($month) }}</span><span class="sub">{{ $month->subMonth()->format('F') }}: {{ $active($month->subMonth()) }}</span></div>
        </section>

        <section>
            <div class="h2row"><h2>By network</h2></div>
            <div class="tablewrap">
                <table class="sales-ch">
                    <thead><tr><th>Network</th><th class="n">Sales</th><th class="n">Orders</th><th class="n">Commission</th><th class="n">Cost of sale</th><th class="n">Pending</th><th class="n">Last month’s sales</th></tr></thead>
                    <tbody>
                        @foreach($networks as $key => $n)
                            <tr>
                                <td>{{ \App\Support\BrandIcon::html($key, 'xs') }}<b>{{ $n['label'] }}</b>@unless($status[$key]['configured'])<span class="sub"> · not connected</span>@endunless</td>
                                <td class="n">{{ Report::money($n['now']?->sales !== null ? (int) $n['now']->sales : null) }}</td>
                                <td class="n">{{ $n['now'] ? number_format($n['now']->orders) : '—' }}</td>
                                <td class="n">{{ Report::money($n['now']?->comm !== null ? (int) $n['now']->comm : null) }}</td>
                                <td class="n">{{ Report::pct($n['now'] && $n['now']->sales > 0 ? $n['now']->comm / $n['now']->sales : null, 1) }}</td>
                                <td class="n">{{ Report::money($n['now']?->pending !== null ? (int) $n['now']->pending : null) }}</td>
                                <td class="n">{{ Report::money($n['prev']?->sales !== null ? (int) $n['prev']->sales : null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <div class="h2row"><h2>Top publishers</h2><span class="sub">{{ $month->format('F') }}, by sales</span></div>
            @if($publishers->isEmpty())
                <div class="empty">No affiliate sales recorded for {{ $month->format('F') }} yet.</div>
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
            <div class="h2row"><h2>Last 12 months</h2><span class="sub">Affiliate sales, both networks</span></div>
            <div class="tablewrap">
                <table class="sales-ch aff-trend">
                    <thead><tr><th>Month</th><th>Sales</th><th class="n">Orders</th><th class="n">Commission</th><th class="n">Cost of sale</th></tr></thead>
                    <tbody>
                        @foreach($trend->reverse() as $t)
                            <tr>
                                <td><a href="{{ route('affiliates', ['month' => $t['month']->format('Y-m')]) }}">{{ $t['month']->format('M Y') }}</a></td>
                                <td><span class="share wide"><i style="width:{{ round($t['sales'] / max(1, $trend->max('sales')) * 100, 1) }}%"></i></span><span class="share-t">{{ Report::money($t['sales']) }}</span></td>
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
