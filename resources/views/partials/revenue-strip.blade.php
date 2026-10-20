{{-- Sales at a glance, for admins and revenue viewers: one line (month to date) that opens to the detail. Totals from GA4; the Sales page has the rest. --}}
@php($hasRev = $revenue['mtd']['has_data'] || $revenue['yesterday']['has_data'])
<section class="rev wrap" aria-label="Sales">
    <details class="rev-d">
        <summary class="rev-sum">
            <span class="rev-name">Sales</span>
            @if($hasRev)
                <span class="rev-l">Month to date</span>
                <b class="rev-big">{{ \App\Support\Revenue\Report::money($revenue['mtd']['cents']) }}</b>
                @include('partials.delta', ['v' => $revenue['mtd']['vs']['cents']])
            @else
                <span class="sub">{{ $revenueStatus['error'] ? 'Can’t read GA4 yet.' : 'Loading sales from GA4… refresh in a minute.' }}</span>
            @endif
            <span class="rev-open">Details<svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></span>
        </summary>
        <div class="rev-in">
            @if($hasRev)
                <div class="rev-fig"><span class="rev-l">Yesterday</span><b>{{ \App\Support\Revenue\Report::money($revenue['yesterday']['cents']) }}</b><span class="sub">{{ number_format($revenue['yesterday']['orders']) }} {{ Str::plural('order', $revenue['yesterday']['orders']) }}</span></div>
                <div class="rev-fig head"><span class="rev-l">Month to date</span><b>{{ \App\Support\Revenue\Report::money($revenue['mtd']['cents']) }}</b>@include('partials.delta', ['v' => $revenue['mtd']['vs']['cents']])@include('partials.pace', ['p' => $revenue['pace'] ?? null])</div>
                <div class="rev-fig"><span class="rev-l">Conversion</span><b>{{ \App\Support\Revenue\Report::pct($revenue['mtd']['conv'], 2) }}</b>@include('partials.delta', ['v' => $revenue['mtd']['vs']['conv'], 'pts' => true])</div>
                @if($revenue['brands']->count() > 1)
                    <ul class="rev-brands" aria-label="Month to date by brand">
                        @foreach($revenue['brands'] as $b)
                            <li><span class="dot" style="background:var(--c{{ $b['brand']->colorSlot() }})"></span>{{ $b['brand']->name }} <b>{{ \App\Support\Revenue\Report::money($b['cents']) }}</b></li>
                        @endforeach
                    </ul>
                @endif
            @endif
            <a class="mini rev-more" href="{{ route('sales') }}">Open Sales →</a>
        </div>
    </details>
</section>
