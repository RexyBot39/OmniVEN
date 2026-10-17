{{-- Change against the 28 days before, as an arrow, number and words. --}}
@if($v === null)
    <span class="delta none">— vs 28 days before</span>
@else
    <span class="delta {{ $v > 0.5 ? 'up' : ($v < -0.5 ? 'down' : 'flat') }}">{{ $v > 0.5 ? '▲' : ($v < -0.5 ? '▼' : '■') }} {{ number_format(abs($v), 1) }}%<span class="delta-w"> vs 28 days before</span></span>
@endif
