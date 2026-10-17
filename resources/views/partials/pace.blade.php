{{-- Progress toward this month's goal, in words and a marker, never color alone. $p from Report::pace(). --}}
@if($p)
    <span class="pace {{ $p['on_pace'] ? 'on' : 'off' }}" title="Goal {{ \App\Support\Revenue\Report::money($p['goal_cents']) }}; {{ round($p['expected'] * 100) }}% of the month has gone">
        <span class="pace-bar"><i style="width:{{ min(100, round($p['share'] * 100, 1)) }}%"></i><b style="left:{{ round($p['expected'] * 100, 1) }}%"></b></span>
        {{ round($p['share'] * 100) }}% of {{ \App\Support\Revenue\Report::money($p['goal_cents']) }} goal · {{ $p['on_pace'] ? 'on pace' : 'behind pace' }}
    </span>
@endif
