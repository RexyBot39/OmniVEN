@if($popup->isNotEmpty())
<dialog id="hotlist-popup" class="popup" aria-labelledby="popup-title">
    <div class="popup-head">
        <span class="flame" aria-hidden="true"></span>
        <h2 id="popup-title">Your Hotlist for today</h2>
    </div>
    <p class="sub">{{ $popup->count() }} {{ \Illuminate\Support\Str::plural('item', $popup->count()) }} assigned to you {{ $popup->count() === 1 ? 'is' : 'are' }} still open.</p>
    <ul class="popup-list">
        @foreach($popup as $t)
            <li class="{{ $t->isOverdue() ? 'overdue' : '' }}">
                <strong>{{ $t->title }}</strong>
                <span class="sub">
                    @if($t->dueLabel())<span class="{{ $t->isOverdue() ? 'late' : '' }}">{{ $t->dueLabel() }}</span> · @endif
                    from {{ $t->creator?->firstName() ?? 'an admin' }}
                </span>
                @if($t->details)<p>{{ \Illuminate\Support\Str::limit($t->details, 180) }}</p>@endif
            </li>
        @endforeach
    </ul>
    <form method="dialog" class="popup-actions">
        <button class="btn" value="tasks" data-goto="tasks" type="submit">Open Tasks</button>
        <button class="btn primary" value="ok" type="submit" autofocus>Got it</button>
    </form>
</dialog>
@endif
