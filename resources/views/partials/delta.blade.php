{{-- Change against the comparison range: an arrow, the number and the words, so it never relies on color. $v is a percent (or points with $pts). $vs names the comparison (default "last year"); $lower marks numbers where down is good (unsubscribes, position). --}}
{{-- Options must be exactly true / a string, so a same-named variable in the including view can't switch them on. --}}
@php($vsText = 'vs '.(is_string($vs ?? null) ? $vs : 'last year'))
@php($asPts = ($pts ?? null) === true)
@php($lowerBetter = ($lower ?? null) === true)
@if($v === null)
    <span class="delta none" title="No data for the comparison dates">— {{ $vsText }}</span>
@else
    @php($good = $lowerBetter ? $v < -0.05 : $v > 0.05)
    @php($bad = $lowerBetter ? $v > 0.05 : $v < -0.05)
    <span class="delta {{ $good ? 'up' : ($bad ? 'down' : 'flat') }}">{{ $v > 0.05 ? '▲' : ($v < -0.05 ? '▼' : '■') }} {{ number_format(abs($v), 1) }}{{ $asPts ? ' pts' : '%' }}<span class="delta-w"> {{ $vsText }}</span></span>
@endif
