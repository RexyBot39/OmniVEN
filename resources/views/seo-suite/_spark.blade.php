{{-- A tiny line of $values (nulls are gaps). $lower: smaller is better (positions), so the line is flipped. --}}
@php
    $vals = array_values($values ?? []);
    $nums = array_filter($vals, fn ($v) => $v !== null);
    $sparkPts = '';
    if (count($nums) > 1) {
        $lo = min($nums);
        $hi = max($nums);
        $span = max(1e-9, $hi - $lo);
        $count = count($vals);
        $pts = [];
        foreach ($vals as $i => $v) {
            if ($v !== null) {
                $t = ($v - $lo) / $span;
                $pts[] = round($i / max(1, $count - 1) * 60, 1).','.round(2 + (($lower ?? false) ? $t : 1 - $t) * 16, 1);
            }
        }
        $sparkPts = implode(' ', $pts);
    }
@endphp
@if($sparkPts !== '')<svg class="seo-spark" width="60" height="20" viewBox="0 0 60 20" aria-hidden="true"><polyline points="{{ $sparkPts }}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/></svg>@endif
