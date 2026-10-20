{{--
    One query's answer: a number, line or bars when the data suits it, and the table (folded under the chart when
    there is one). $res is a Result, $as the display wanted (auto, table, line, bar, scalar), $name names the measure.
    Shown only; nothing is cached or saved.
--}}
@if(! $res->ok())
    <div class="callout warn-callout mb-err" role="status"><div><b>The query didn’t run.</b> {{ $res->error }}</div></div>
@elseif($res->count === 0 && ! $res->capped)
    <div class="empty">No rows.</div>
@else
    @php
        $shape = $res->display($as ?? 'auto');
        $m = $res->measure();
        $limit = (int) config('metabase.max_rows');
        $shown = array_slice($res->rows, 0, $limit);
    @endphp
    @if($shape === 'scalar')
        @php $ni = $res->numberColumns()[0] ?? 0; @endphp
        <div class="mb-scalar"><span class="tv">{{ $res->format($res->rows[0][$ni] ?? null, $ni) }}</span><span class="sub">{{ $res->cols[$ni]['name'] ?? '' }}</span></div>
    @elseif($shape === 'line')
        @include('partials.trend', ['points' => $res->points(), 'fmt' => $res->wholeNumbers() ? 'int' : 'dec', 'name' => $res->cols[$m]['name'] ?? ($name ?? 'Value'), 'cmp' => null, 'grain' => 'day', 'lower' => false])
    @elseif($shape === 'bar')
        @php
            $bars = array_slice($res->rows, 0, 40);
            $top = max(1, max(array_map(fn ($r) => is_numeric($r[$m] ?? null) ? abs((float) $r[$m]) : 0, $bars)));
        @endphp
        <ul class="mb-bars" aria-label="{{ $res->cols[$m]['name'] ?? '' }} by {{ $res->cols[0]['name'] ?? '' }}">
            @foreach($bars as $r)
                <li><span class="mb-bar-l">{{ $res->format($r[0] ?? null, 0) }}</span><span class="mb-bar"><i style="width:{{ round(abs((float) ($r[$m] ?? 0)) / $top * 100, 1) }}%"></i></span><span class="mb-bar-n">{{ $res->format($r[$m] ?? null, $m) }}</span></li>
            @endforeach
        </ul>
    @endif

    {{-- The line chart has its own "Show as table"; a single number needs none. --}}
    @if(! in_array($shape, ['scalar', 'line'], true))
    @if($shape !== 'table')<details class="mb-tbl"><summary class="mini">Show as table ({{ number_format($res->count) }} {{ $res->count === 1 ? 'row' : 'rows' }})</summary>@endif
    <div class="tablewrap">
        <table class="sales-ch mb-table">
            <thead><tr>@foreach($res->cols as $i => $c)<th @class(['n' => $c['kind'] === 'number'])>{{ $c['name'] }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($shown as $r)
                    <tr>@foreach($res->cols as $i => $c)<td @class(['n' => $c['kind'] === 'number'])>{{ $res->format($r[$i] ?? null, $i) }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($shape !== 'table')</details>@endif
    @endif
    @if($res->capped || $res->maskedColumns())
        <p class="sub mb-guard">@if($res->capped)OmniVEN shows at most {{ number_format(config('metabase.max_rows')) }} rows; group or filter the query to see the rest, or open it in Metabase. @endif @if($res->maskedColumns())Personal details are masked: {{ implode(', ', $res->maskedColumns()) }}.@endif</p>
    @endif
@endif
