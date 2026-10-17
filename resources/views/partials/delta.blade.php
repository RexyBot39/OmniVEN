{{-- Change on last year: an arrow, the number and the words, so it never relies on color. $v is a percent (or points with $pts). --}}
@if($v === null)
    <span class="delta none" title="No data for the same dates last year">— vs last year</span>
@else
    <span class="delta {{ $v > 0.05 ? 'up' : ($v < -0.05 ? 'down' : 'flat') }}">{{ $v > 0.05 ? '▲' : ($v < -0.05 ? '▼' : '■') }} {{ number_format(abs($v), 1) }}{{ ! empty($pts) ? ' pts' : '%' }}<span class="delta-w"> vs last year</span></span>
@endif
