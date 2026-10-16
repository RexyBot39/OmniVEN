{{-- My launchpad: this person's stars, in the order they arranged them, and recently opened items.
     The same stars come first in Jump to (⌘K). $arranging shows the reorder controls. --}}
@php($arranging = $arranging ?? false)
<section class="launchpad wrap {{ $arranging ? 'arranging' : '' }}" id="launchpad" aria-label="My launchpad" data-order="{{ route('launchpad.order') }}">
    <div class="lp-row {{ $launchpad['favorites']->isEmpty() ? 'lp-empty' : '' }}" @if($arranging) role="list" aria-label="Your stars, in order" @endif>
        <span class="lp-h">My launchpad</span>
        @forelse($launchpad['favorites'] as $i)
            @if($arranging)
                <span class="lp-chip lp-move" role="listitem" data-key="{{ $i['key'] }}" draggable="true">
                    <button type="button" class="lp-mv" data-mv="-1" aria-label="Move {{ $i['t'] }} earlier" @disabled($loop->first)>‹</button>
                    @if($i['ic']){{ \App\Support\BrandIcon::html($i['ic'], 'sm', $i['t']) }}@else<span class="lp-ini" style="--c:var(--c{{ $i['c'] }})">{{ $i['ini'] }}</span>@endif
                    <span class="lp-t">{{ $i['t'] }}</span>
                    <button type="button" class="lp-mv" data-mv="1" aria-label="Move {{ $i['t'] }} later" @disabled($loop->last)>›</button>
                </span>
            @else
                <a class="lp-chip" href="{{ $i['u'] }}" data-lp="{{ $i['key'] }}" @if($i['x']) target="_blank" rel="noopener noreferrer" @endif title="{{ $i['s'] }}">
                    @if($i['ic']){{ \App\Support\BrandIcon::html($i['ic'], 'sm', $i['t']) }}@else<span class="lp-ini" style="--c:var(--c{{ $i['c'] }})">{{ $i['ini'] }}</span>@endif
                    <span class="lp-t">{{ $i['t'] }}</span>@if($i['x'])<span class="lp-ext" aria-hidden="true">↗</span>@endif
                </a>
            @endif
        @empty
            <span class="sub">Star ☆ any page, tool category, brand, account or tool to pin it here and at the top of Jump to.</span>
        @endforelse
        @if($launchpad['favorites']->count() > 1)
            <button type="button" class="lp-arrange" data-arrange aria-pressed="{{ $arranging ? 'true' : 'false' }}">{{ $arranging ? 'Done' : 'Arrange' }}</button>
        @endif
    </div>
    @if($arranging)
        <p class="sub lp-tip">Drag a star, or use ‹ ›, to change the order here and in Jump to. It saves as you go.</p>
    @elseif($launchpad['recent']->isNotEmpty())
        <div class="lp-row lp-recent">
            <span class="lp-h">Recent</span>
            @foreach($launchpad['recent'] as $i)
                <a href="{{ $i['u'] }}" data-lp="{{ $i['key'] }}" @if($i['x']) target="_blank" rel="noopener noreferrer" @endif title="{{ $i['s'] }}">{{ \App\Support\BrandIcon::html($i['ic'], 'xs') }}{{ $i['t'] }}</a>
            @endforeach
        </div>
    @endif
</section>
