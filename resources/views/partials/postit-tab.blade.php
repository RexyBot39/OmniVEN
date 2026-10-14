{{-- ================= POSTIT NOTES ================= --}}
<section data-view="postit" hidden>
    <div class="h2row">
        <h2>PostIT Notes</h2>
        <span class="sub">Your own to-do lists. Only you can see them.</span>
    </div>

    <form method="POST" action="{{ route('postits.store') }}" class="new-postit">
        @csrf
        <input type="text" name="title" required maxlength="80" placeholder="New note title, e.g. This week" aria-label="New note title">
        <div class="swatches" role="radiogroup" aria-label="Color">
            @foreach(config('omniven.postit_colors') as $key => $label)
                <label class="swatch"><input type="radio" name="color" value="{{ $key }}" @checked($loop->first)><span class="sw pi-{{ $key }}" title="{{ $label }}"></span><span class="sr">{{ $label }}</span></label>
            @endforeach
        </div>
        <button class="btn primary" type="submit">+ Add note</button>
    </form>

    @if($postits->isEmpty())
        <div class="empty">No notes yet. Give your first one a title above, then add to-dos to it.</div>
    @else
        <div class="postits">
            @foreach($postits as $n)
                @php($open = $n->items->whereNull('done_at')->count())
                <article class="postit pi-{{ $n->color }}">
                    <header>
                        <h3>{{ $n->title }}</h3>
                        <span class="pi-count">{{ $open }} left</span>
                    </header>
                    <ul>
                        @foreach($n->items as $it)
                            <li class="{{ $it->done_at ? 'done' : '' }}">
                                <form method="POST" action="{{ route('postits.items.toggle', $it) }}">@csrf
                                    <button class="pi-check" type="submit" aria-pressed="{{ $it->done_at ? 'true' : 'false' }}" aria-label="{{ $it->done_at ? 'Uncheck' : 'Check off' }}: {{ $it->body }}"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                                </form>
                                <span class="pi-text">{{ $it->body }}</span>
                                <form method="POST" action="{{ route('postits.items.destroy', $it) }}">@csrf @method('DELETE')
                                    <button class="pi-x" type="submit" aria-label="Delete: {{ $it->body }}">×</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('postits.items.store', $n) }}" class="pi-add">@csrf
                        <input type="text" name="body" required maxlength="500" placeholder="Add a to-do…" aria-label="Add a to-do to {{ $n->title }}">
                    </form>
                    <footer>
                        @if($n->items->whereNotNull('done_at')->count())
                            <form method="POST" action="{{ route('postits.clear', $n) }}">@csrf<button class="pi-link" type="submit">Clear done</button></form>
                        @endif
                        <span class="spacer"></span>
                        <form method="POST" action="{{ route('postits.destroy', $n) }}" data-confirm>@csrf @method('DELETE')
                            <button class="pi-link" type="submit" data-label="Delete note">Delete note</button>
                        </form>
                    </footer>
                </article>
            @endforeach
        </div>
    @endif
</section>
