{{--
    A line chart of one measure over the picked range, with the comparison range as a dashed line.
    $points from DateRange::series(); $fmt is money (cents), int, pct (0–1) or dec; $name names the measure;
    $cmp names the comparison range (null for none); $grain is day, week or month. Hover shows both values; "Show as table" lists them.
--}}
@php
    $fmtFn = match ($fmt ?? 'int') {
        'money' => fn ($v) => $v === null ? '—' : '$'.number_format($v / 100, 0),
        'pct' => fn ($v) => $v === null ? '—' : number_format($v * 100, 2).'%',
        'dec' => fn ($v) => $v === null ? '—' : number_format($v, 1),
        default => fn ($v) => $v === null ? '—' : number_format((float) $v),
    };
    $n = count($points);
    $hasCmp = ! empty($cmp) && collect($points)->contains(fn ($p) => $p['c'] !== null);
    $top = max(1, collect($points)->flatMap(fn ($p) => [$p['v'], $p['c'] ?? 0])->max() ?? 1);
    // A round top for the axis: 1, 2, 2.5 or 5 times a power of ten.
    $mag = 10 ** floor(log10($top));
    $nice = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($v) => $v >= $top) ?? $top;
    $x = fn ($i) => $n > 1 ? round($i / ($n - 1) * 1000, 2) : 500;
    $y = fn ($v) => round(300 - ($v ?? 0) / $nice * 300, 2);
    $line = fn ($key, $from = 0, $to = null) => collect($points)->slice($from, $to === null ? null : $to - $from + 1)->map(fn ($p, $i) => $p[$key] === null ? null : $x($i).','.$y($p[$key]))->filter()->implode(' ');
    // Cut-short weeks or months at either end are drawn dotted, so a part-week doesn't read as a drop.
    $firstFull = ($points[0]['partial'] ?? false) && $n > 1 ? 1 : 0;
    $lastFull = ($points[$n - 1]['partial'] ?? false) && $n > 1 ? $n - 2 : $n - 1;
    $hasPartial = $firstFull > 0 || $lastFull < $n - 1;
    $tickEvery = max(1, (int) ceil($n / 6));
    $data = collect($points)->map(fn ($p) => ['l' => $p['label'], 'v' => $fmtFn($p['v']), 'c' => $hasCmp && $p['c'] !== null ? $fmtFn($p['c']) : null, 'y' => $p['v'] === null ? null : round($p['v'] / $nice, 4)])->values();
@endphp
<figure class="trend" data-trend="{{ json_encode(['name' => $name, 'cmp' => $hasCmp ? $cmp : null, 'pts' => $data], JSON_UNESCAPED_UNICODE) }}">
    <figcaption class="trend-legend">
        <span class="tl-now"><i aria-hidden="true"></i>{{ $name }}</span>
        @if($hasCmp)<span class="tl-cmp"><i aria-hidden="true"></i>{{ $cmp }}</span>@endif
        @if($hasPartial)<span class="tl-part"><i aria-hidden="true"></i>Part {{ $grain ?? 'period' }} (fewer days)</span>@endif
    </figcaption>
    <div class="trend-plot">
        <div class="trend-y" aria-hidden="true">
            @foreach([1, 0.5, 0] as $f)<span style="top:{{ (1 - $f) * 100 }}%">{{ $fmtFn($nice * $f) }}</span>@endforeach
        </div>
        <div class="trend-area" tabindex="0" aria-label="Chart: use the left and right arrow keys to read each point">
            <svg viewBox="0 0 1000 300" preserveAspectRatio="none" role="img" aria-label="{{ $name }} by {{ $grain ?? 'day' }}{{ $hasCmp ? ', compared with '.$cmp : '' }}">
                @foreach([0, 150] as $gy)<line x1="0" x2="1000" y1="{{ $gy }}" y2="{{ $gy }}" class="grid"/>@endforeach
                <line x1="0" x2="1000" y1="300" y2="300" class="base"/>
                @if($hasCmp)<polyline points="{{ $line('c') }}" class="cmp"/>@endif
                @if($firstFull > 0)<polyline points="{{ $line('v', 0, 1) }}" class="now part"/>@endif
                <polyline points="{{ $line('v', $firstFull, $lastFull) }}" class="now"/>
                @if($lastFull < $n - 1)<polyline points="{{ $line('v', $n - 2, $n - 1) }}" class="now part"/>@endif
            </svg>
            <span class="trend-cross" hidden></span>
            <span class="trend-dot" hidden></span>
            <div class="trend-tip" role="status" hidden></div>
        </div>
        <div class="trend-x" aria-hidden="true">
            @foreach($points as $i => $p)
                @if($i % $tickEvery === 0)<span style="left:{{ $x($i) / 10 }}%">{{ $p['short'] }}</span>@endif
            @endforeach
        </div>
    </div>
    <details class="trend-table">
        <summary>Show as table</summary>
        <div class="tablewrap">
            <table>
                <thead><tr><th>{{ ucfirst($grain ?? 'day') }}</th><th class="n">{{ $name }}</th>@if($hasCmp)<th class="n">{{ $cmp }}</th><th class="n">Change</th>@endif</tr></thead>
                <tbody>
                    @foreach($points as $p)
                        <tr><td>{{ $p['label'] }}</td><td class="n">{{ $fmtFn($p['v']) }}</td>@if($hasCmp)<td class="n">{{ $p['c'] === null ? '—' : $fmtFn($p['c']) }}</td><td class="n">@include('partials.delta', ['v' => \App\Support\DateRange::change($p['v'], $p['c']), 'vs' => 'comparison', 'lower' => ($lower ?? null) === true])</td>@endif</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
