@extends('layouts.app', ['title' => 'Email & SMS'])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Sales</span><span>From {{ \App\Support\BrandIcon::html('klaviyo', 'xs') }}Klaviyo</span>@if($status['last'])<span>Updated {{ $status['last']->format('M j, g:i A') }}</span>@endif</div>
            <h1 class="form-h">Email &amp; SMS @include('partials.star', ['key' => 'page:19', 'label' => 'Email & SMS'])</h1>
        </div>
        @include('partials.sales-nav')
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect Klaviyo to see email and SMS health here.</b></p>
            @can('admin')
                <ol>
                    <li>In Klaviyo, go to Settings → API keys → Create private API key. Choose <b>Custom</b> and give it <b>Read</b> access to Campaigns, Flows, Lists and Metrics only.</li>
                    <li>In Railway, set <code>KLAVIYO_PRIVATE_KEY</code> to it. The order event and main list are already filled in.</li>
                </ol>
            @else
                <p class="sub">Ask an admin to connect it.</p>
            @endcan
        </div>
    @elseif(! $r)
        <div class="empty">{{ $status['error'] ? 'Can’t read Klaviyo yet.' : 'Loading from Klaviyo… refresh in a minute.' }}@can('admin') {{ $status['error'] }}@endcan</div>
    @else
        @if($status['error'])
            <div class="callout warn-callout" role="status"><div><b>The last update from Klaviyo failed.</b> Showing numbers from {{ $status['last']?->format('M j, g:i A') }}.@can('admin') {{ $status['error'] }}@endcan</div></div>
        @endif

        @if($checks)
            <section aria-label="Needs attention">
                <ul class="em-checks">
                    @foreach($checks as [$level, $text])<li class="{{ $level }}"><b>{{ $level === 'bad' ? 'Fix' : 'Watch' }}</b> {{ $text }}</li>@endforeach
                </ul>
            </section>
        @endif

        @include('email.history')

        <div class="h2row em-30"><h2>Last 30 days in detail</h2><span class="sub">Each campaign and flow, from Klaviyo’s reports</span></div>
        <section class="tiles sales-tiles" aria-label="Last 30 days">
            <div class="tile"><span class="tl">Buyers from email &amp; SMS</span><span class="tv">{{ number_format($campaignTotals['orders'] + $r['flow_totals']['orders']) }}</span><span class="sub">{{ number_format($campaignTotals['orders']) }} campaigns · {{ number_format($r['flow_totals']['orders']) }} flows</span></div>
            <div class="tile"><span class="tl">Revenue from email &amp; SMS</span><span class="tv">{{ $r['values_ok'] ? Report::money((int) (collect($r['campaigns'])->sum('value_cents') + ($r['flow_totals']['value_cents'] ?? 0))) : '—' }}</span><span class="sub">{{ $r['values_ok'] ? 'Credited by Klaviyo' : 'Orders carry no value yet' }}</span></div>
            <div class="tile"><span class="tl">Campaign open rate</span><span class="tv">{{ Report::pct($rates['open'], 1) }}</span><span class="sub">Click rate {{ Report::pct($rates['click'], 2) }}</span></div>
            <div class="tile"><span class="tl">Unsubscribe rate</span><span class="tv">{{ Report::pct($rates['unsub'], 2) }}</span><span class="sub">Spam {{ Report::pct($rates['spam'], 3) }}</span></div>
            <div class="tile"><span class="tl">Main list</span><span class="tv">{{ $r['profiles'] !== null ? number_format($r['profiles']) : '—' }}</span><span class="sub">{{ $growth === null ? 'Growth shows after 30 days of history' : ($growth >= 0 ? '+' : '−').number_format(abs($growth)).' in 30 days' }}</span></div>
        </section>

        <section>
            <div class="h2row"><h2>Campaigns</h2><span class="sub">Sent to {{ number_format(config('klaviyo.min_recipients')) }}+ people in the last 30 days; tests left out</span></div>
            @if(empty($r['campaigns']))
                <div class="empty">No campaigns in the last 30 days.</div>
            @else
                <div class="tablewrap">
                    <table class="sales-ch">
                        <thead><tr><th>Campaign</th><th class="n">Recipients</th><th class="n">Open</th><th class="n">Click</th><th class="n">Buyers</th>@if($r['values_ok'])<th class="n">Revenue</th>@endif<th class="n">Unsub</th></tr></thead>
                        <tbody>
                            @foreach($r['campaigns'] as $c)
                                <tr>
                                    <td><b>{{ $c['name'] }}</b><div class="sub">{{ strtoupper($c['channel']) === 'SMS' ? 'SMS' : 'Email' }}{{ $c['sent'] ? ' · '.\Carbon\Carbon::parse($c['sent'])->timezone(config('app.timezone'))->format('M j') : '' }}</div></td>
                                    <td class="n">{{ number_format($c['recipients']) }}</td>
                                    <td class="n">{{ Report::pct($c['delivered'] ? $c['opens'] / $c['delivered'] : null, 1) }}</td>
                                    <td class="n">{{ Report::pct($c['delivered'] ? $c['clicks'] / $c['delivered'] : null, 2) }}</td>
                                    <td class="n">{{ number_format($c['orders']) }}</td>
                                    @if($r['values_ok'])<td class="n">{{ Report::money($c['value_cents']) }}</td>@endif
                                    <td class="n {{ $c['delivered'] && $c['unsubs'] / $c['delivered'] > config('klaviyo.warn.unsubscribe_rate') ? 'late' : '' }}">{{ Report::pct($c['delivered'] ? $c['unsubs'] / $c['delivered'] : null, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section>
            <div class="h2row"><h2>Flows</h2><span class="sub">Automated emails, by buyers credited in the last 30 days</span></div>
            <div class="tablewrap">
                <table class="sales-ch">
                    <thead><tr><th>Flow</th><th class="n">Recipients</th><th class="n">Open</th><th class="n">Click</th><th class="n">Buyers</th>@if($r['values_ok'])<th class="n">Revenue</th>@endif</tr></thead>
                    <tbody>
                        @foreach($r['flows'] as $f)
                            <tr>
                                <td><b>{{ $f['name'] }}</b></td>
                                <td class="n">{{ number_format($f['recipients']) }}</td>
                                <td class="n">{{ Report::pct($f['delivered'] ? $f['opens'] / $f['delivered'] : null, 1) }}</td>
                                <td class="n">{{ Report::pct($f['delivered'] ? $f['clicks'] / $f['delivered'] : null, 2) }}</td>
                                <td class="n">{{ number_format($f['orders']) }}</td>
                                @if($r['values_ok'])<td class="n">{{ Report::money($f['value_cents']) }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="sub sales-note">Buyers are people who ordered within Klaviyo’s attribution window after a message (each counted once per message). Open rates include Apple Mail privacy opens, so use clicks and orders to compare messages.</p>
        </section>
    @endif
</main>
@endsection
