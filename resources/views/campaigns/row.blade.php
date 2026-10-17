<li class="card or-row cmp-row st-{{ $c->state() }}" style="border-left-color:{{ $c->brand ? 'var(--c'.$c->brand->colorSlot().')' : 'var(--line)' }}">
    <div class="or-main">
        <a class="or-name" href="{{ route('campaigns.show', $c) }}">{{ $c->name }}</a>
        <span class="sub">{{ $c->dateLabel() }}@if($c->brand) · {{ $c->brand->name }}@endif · {{ $c->owner?->firstName() ?? 'No owner' }}@if($c->goal) · {{ \Illuminate\Support\Str::limit($c->goal, 70) }}@endif</span>
    </div>
    <span class="or-when">{{ $c->tasks_count ? $c->done_count.' of '.$c->tasks_count.' tasks done' : 'No tasks yet' }}</span>
    <span class="or-when">{{ $c->links_count }} {{ \Illuminate\Support\Str::plural('link', $c->links_count) }}</span>
</li>
