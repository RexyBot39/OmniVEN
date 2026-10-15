{{-- Your stuck PostIT notes, floating in the top right of every page. Only you see them. --}}
@if($stuckPostits->isNotEmpty())
<aside class="stuck-postits" aria-label="Your stuck PostIT notes">
    @foreach($stuckPostits as $n)
        @php($open = $n->items->whereNull('done_at')->count())
        <details class="postit stuck pi-{{ $n->color }}" open data-stuck="{{ $n->id }}">
            <summary><h3>{{ $n->title }}</h3><span class="pi-count">{{ $open }} left</span></summary>
            <ul>
                @foreach($n->items as $it)
                    <li class="{{ $it->done_at ? 'done' : '' }}">
                        <form method="POST" action="{{ route('postits.items.toggle', $it) }}">@csrf<input type="hidden" name="from_sticky" value="1">
                            <button class="pi-check" type="submit" aria-pressed="{{ $it->done_at ? 'true' : 'false' }}" aria-label="{{ $it->done_at ? 'Uncheck' : 'Check off' }}: {{ $it->body }}"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                        </form>
                        <span class="pi-text">{{ $it->body }}</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('postits.items.store', $n) }}" class="pi-add">@csrf<input type="hidden" name="from_sticky" value="1">
                <input type="text" name="body" required maxlength="500" placeholder="Add a to-do…" aria-label="Add a to-do to {{ $n->title }}">
            </form>
            <footer>
                <span class="spacer"></span>
                <form method="POST" action="{{ route('postits.stick', $n) }}">@csrf<input type="hidden" name="from_sticky" value="1">
                    <button class="pi-link" type="submit">Unstick</button>
                </form>
            </footer>
        </details>
    @endforeach
</aside>
@endif
