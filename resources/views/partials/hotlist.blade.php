{{-- Quick strip of flagged tasks that aren't done yet. Shown on every tab. --}}
<section class="hotlist" aria-label="Hotlist">
    <div class="hot-head">
        <span class="hot-title"><span class="flame" aria-hidden="true"></span>Hotlist</span>
        <span class="sub">{{ $hotlist->count() ? $hotlist->count().' open · '.$hotlist->where('assignee_id', auth()->id())->count().' yours' : 'Nothing urgent right now' }}</span>
        @if($hotlist->count())<button type="button" class="mini" data-goto="tasks">See all tasks →</button>@endif
    </div>
    @if($hotlist->isNotEmpty())
        <ul class="hot-items">
            @foreach($hotlist->take(8) as $t)
                @php($mine = $t->assignee_id === auth()->id())
                <li class="hot-item {{ $mine ? 'mine' : '' }} {{ $t->isOverdue() ? 'overdue' : '' }}">
                    @can('complete-task', $t)
                        <form method="POST" action="{{ route('tasks.toggle', $t) }}">
                            @csrf<input type="hidden" name="return" value="hotlist">
                            <button class="tick" type="submit" aria-pressed="false" aria-label="Mark done: {{ $t->title }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                        </form>
                    @else
                        <span class="dot-hot" aria-hidden="true"></span>
                    @endcan
                    <div class="hot-body">
                        <span class="hot-t">{{ $t->title }}</span>
                        <span class="hot-meta">{{ $mine ? 'You' : $t->assignee->firstName() }}@if($t->dueLabel()) · <span class="{{ $t->isOverdue() ? 'late' : '' }}">{{ $t->dueLabel() }}</span>@endif</span>
                    </div>
                </li>
            @endforeach
        </ul>
        @if($hotlist->count() > 8)<p class="sub more">+ {{ $hotlist->count() - 8 }} more on the Tasks tab</p>@endif
    @endif
</section>
