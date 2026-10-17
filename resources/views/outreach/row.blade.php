<li class="card or-row st-{{ $p->stage }} {{ $p->followUpDue() ? 'due' : '' }}">
    <div class="or-main">
        <a class="or-name" href="{{ route('outreach.show', $p) }}">{{ $p->name }}</a>
        <span class="sub">{{ $p->host() ?? config('outreach.types.'.$p->type) }}@if($p->brand) · {{ $p->brand->name }}@endif · {{ $p->owner?->firstName() ?? 'No owner' }}</span>
    </div>
    <span class="or-stage">{{ $p->stageLabel() }}</span>
    <span class="or-when {{ $p->followUpDue() ? 'late' : '' }}">
        @if($p->isOpen() && $p->follow_up_on)
            {{ $p->followUpDue() ? ($p->follow_up_on->isToday() ? 'Follow up today' : 'Follow up — was due '.$p->follow_up_on->format('M j')) : 'Follow up '.$p->follow_up_on->format('M j') }}
        @elseif($p->stage === 'idea')
            Not contacted yet
        @elseif($p->last_contacted_on)
            Last contact {{ $p->last_contacted_on->format('M j') }}
        @endif
    </span>
    @if($quick)
        <form method="POST" action="{{ route('outreach.act', $p) }}" class="or-quick">
            @csrf
            <button class="btn" type="submit" name="action" value="nudged">Followed up</button>
            <button class="btn" type="submit" name="action" value="replied">They replied</button>
        </form>
    @endif
</li>
