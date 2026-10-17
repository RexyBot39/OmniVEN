{{-- Sales at a glance, for admins and revenue viewers. Totals from GA4; the Sales page has the detail. --}}
<section class="rev wrap" aria-label="Sales">
    <div class="rev-in">
        <a class="rev-name" href="{{ route('sales') }}">Sales</a>
        @if($revenue['mtd']['has_data'] || $revenue['yesterday']['has_data'])
            <div class="rev-fig"><span class="rev-l">Yesterday</span><b>{{ \App\Support\Revenue\Report::money($revenue['yesterday']['cents']) }}</b><span class="sub">{{ number_format($revenue['yesterday']['orders']) }} {{ Str::plural('order', $revenue['yesterday']['orders']) }}</span></div>
            <div class="rev-fig head"><span class="rev-l">Month to date</span><b>{{ \App\Support\Revenue\Report::money($revenue['mtd']['cents']) }}</b>@include('partials.delta', ['v' => $revenue['mtd']['vs']['cents']])</div>
            <div class="rev-fig"><span class="rev-l">Conversion</span><b>{{ \App\Support\Revenue\Report::pct($revenue['mtd']['conv'], 2) }}</b>@include('partials.delta', ['v' => $revenue['mtd']['vs']['conv'], 'pts' => true])</div>
            @if($revenue['brands']->count() > 1)
                <ul class="rev-brands" aria-label="Month to date by brand">
                    @foreach($revenue['brands'] as $b)
                        <li><span class="dot" style="background:var(--c{{ $b['brand']->colorSlot() }})"></span>{{ $b['brand']->name }} <b>{{ \App\Support\Revenue\Report::money($b['cents']) }}</b></li>
                    @endforeach
                </ul>
            @endif
        @else
            <span class="sub">{{ $revenueStatus['error'] ? 'Can’t read GA4 yet.' : 'Loading sales from GA4… refresh in a minute.' }}</span>
        @endif
        <a class="mini rev-more" href="{{ route('sales') }}">By channel →</a>
    </div>
</section>
