@use('App\Support\Revenue\Report')
{{-- Email & SMS over any date range, from the daily history (campaigns and flows), with the comparison. --}}
@php
    $e = $hist['email'] ?? null;
    $pe = $histPrev['email'] ?? null;
    $chg = fn ($k) => \App\Support\DateRange::change($e[$k] ?? null, $pe[$k] ?? null);
    $ptsChange = fn ($k) => isset($e[$k], $pe[$k]) ? ($e[$k] - $pe[$k]) * 100 : null;
    $q = fn (array $extra = []) => $range->query(array_merge(['metric' => $metric !== 'received' ? $metric : null], $extra));
@endphp
<section class="em-hist" id="history">
    <div class="h2row"><h2>Over any dates</h2><span class="sub">Campaigns and flows together, from Klaviyo’s daily activity</span></div>
    @include('partials.range-picker', ['range' => $range, 'route' => 'email', 'keep' => ['metric' => $metric !== 'received' ? $metric : null]])
    @if($histStatus['error'])
        <div class="callout warn-callout" role="status"><div><b>Loading history from Klaviyo failed.</b>@can('admin') {{ $histStatus['error'] }}@endcan</div></div>
    @endif
    @if(! $e)
        <div class="empty">{{ $histStatus['history_from'] ? 'No email activity in these dates.' : 'Daily history is loading from Klaviyo (up to five years, a year at a time). Refresh in a few minutes.' }}</div>
    @else
        <div class="tiles sales-tiles">
            <div class="tile"><span class="tl">Emails received</span><span class="tv">{{ number_format($e['received']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $chg('received'), 'vs' => $vsLabel])<span class="tile-was">{{ $pe ? number_format($pe['received']) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Open rate</span><span class="tv">{{ Report::pct($e['open_rate'], 1) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $ptsChange('open_rate'), 'pts' => true, 'vs' => $vsLabel])<span class="tile-was">{{ $pe ? Report::pct($pe['open_rate'], 1) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Click rate</span><span class="tv">{{ Report::pct($e['click_rate'], 2) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $ptsChange('click_rate'), 'pts' => true, 'vs' => $vsLabel])<span class="tile-was">{{ $pe ? Report::pct($pe['click_rate'], 2) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Orders from email &amp; SMS</span><span class="tv">{{ number_format($e['orders']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $chg('orders'), 'vs' => $vsLabel])<span class="tile-was">{{ $pe ? number_format($pe['orders']) : 'No data' }} then</span>@endif</div>
            <div class="tile"><span class="tl">Revenue from email &amp; SMS</span><span class="tv">{{ $e['revenue_cents'] > 0 ? Report::money($e['revenue_cents']) : '—' }}</span>@if($vsLabel && $e['revenue_cents'] > 0)@include('partials.delta', ['v' => $chg('revenue_cents'), 'vs' => $vsLabel])<span class="tile-was">{{ $pe ? Report::money($pe['revenue_cents']) : 'No data' }} then</span>@elseif($e['revenue_cents'] === 0)<span class="sub">Orders carry no value in Klaviyo</span>@endif</div>
            <div class="tile"><span class="tl">Unsubscribe rate</span><span class="tv">{{ Report::pct($e['unsub_rate'], 2) }}</span>@if($vsLabel)@include('partials.delta', ['v' => $ptsChange('unsub_rate'), 'pts' => true, 'vs' => $vsLabel, 'lower' => true])<span class="tile-was">{{ number_format($e['unsubscribed']) }} people · spam {{ Report::pct($e['spam_rate'], 3) }}</span>@endif</div>
            <div class="tile"><span class="tl">New subscribers</span><span class="tv">{{ number_format($hist['subscribed']) }}</span>@if($vsLabel)@include('partials.delta', ['v' => \App\Support\DateRange::change($hist['subscribed'], $histPrev['subscribed'] ?? null), 'vs' => $vsLabel])<span class="tile-was">{{ $histPrev ? number_format($histPrev['subscribed']) : 'No data' }} then</span>@endif</div>
        </div>

        <div class="tablewrap">
            <table class="sales-ch">
                <thead><tr><th></th><th class="n">Received</th><th class="n">Open rate</th><th class="n">Click rate</th><th class="n">Orders</th><th class="n">Revenue</th><th class="n">Per email</th><th class="n">Unsub rate</th>@if($vsLabel)<th class="n">Orders vs {{ $vsLabel }}</th>@endif</tr></thead>
                <tbody>
                    @foreach(['campaign' => 'Campaigns', 'flow' => 'Flows', 'email' => 'Total'] as $k => $label)
                        @php $r2 = $hist[$k]; $p2 = $histPrev[$k] ?? null; @endphp
                        <tr @if($k === 'email') class="em-total" @endif>
                            <td><b>{{ $label }}</b></td>
                            <td class="n">{{ number_format($r2['received']) }}</td>
                            <td class="n">{{ Report::pct($r2['open_rate'], 1) }}</td>
                            <td class="n">{{ Report::pct($r2['click_rate'], 2) }}</td>
                            <td class="n">{{ number_format($r2['orders']) }}</td>
                            <td class="n">{{ $r2['revenue_cents'] > 0 ? Report::money($r2['revenue_cents']) : '—' }}</td>
                            <td class="n">{{ $r2['rev_per_email_cents'] && $r2['revenue_cents'] > 0 ? '$'.number_format($r2['rev_per_email_cents'] / 100, 3) : '—' }}</td>
                            <td class="n">{{ Report::pct($r2['unsub_rate'], 2) }}</td>
                            @if($vsLabel)<td class="n">@include('partials.delta', ['v' => \App\Support\DateRange::change($r2['orders'], $p2['orders'] ?? null), 'vs' => $vsLabel])</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($hist['all_orders'] > 0)
            <p class="sub sales-note">Of {{ number_format($hist['all_orders']) }} order events Klaviyo received in these dates, {{ Report::pct($e['orders'] / $hist['all_orders'], 0) }} were credited to an email or SMS. Opens, clicks and unsubscribes are counted once per person per day.</p>
        @endif

        <div class="h2row"><h3>Over time</h3>
            <nav class="seg" aria-label="Measure">
                @foreach(\App\Support\Email\EmailHistory::CHART as $k => [$label])
                    <a class="seg-b" href="{{ route('email', $q(['metric' => $k === 'received' ? null : $k])) }}#history" aria-pressed="{{ $metric === $k ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        @include('partials.trend', ['points' => $histTrend, 'fmt' => \App\Support\Email\EmailHistory::CHART[$metric][1], 'name' => \App\Support\Email\EmailHistory::CHART[$metric][0].', '.\App\Support\DateRange::span($range->from, $range->to), 'cmp' => $range->compareLabel(), 'grain' => $range->grain(), 'lower' => $metric === 'unsubscribed'])
    @endif
</section>
