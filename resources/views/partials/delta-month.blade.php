{{-- Change on last month, in percentage points, with an arrow and words so it never relies on color. --}}
@if($now === null || $then === null)
    <span class="delta none">— vs last month</span>
@else
    @php $d = ($now - $then) * 100; @endphp
    <span class="delta {{ $d > 0.5 ? 'up' : ($d < -0.5 ? 'down' : 'flat') }}">{{ $d > 0.5 ? '▲' : ($d < -0.5 ? '▼' : '■') }} {{ number_format(abs($d), 0) }} pts<span class="delta-w"> vs last month</span></span>
@endif
