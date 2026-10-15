{{-- My launchpad: this person's stars (in the order starred) and recently opened items. --}}
<section class="launchpad wrap" id="launchpad" aria-label="My launchpad">
    <div class="lp-row">
        <span class="lp-h">My launchpad</span>
        @forelse($launchpad['favorites'] as $i)
            <a class="lp-chip" href="{{ $i['u'] }}" data-lp="{{ $i['key'] }}" @if($i['x']) target="_blank" rel="noopener noreferrer" @endif title="{{ $i['s'] }}">
                @if($i['ic']){{ \App\Support\BrandIcon::html($i['ic'], 'sm', $i['t']) }}@else<span class="lp-ini" style="--c:var(--c{{ $i['c'] }})">{{ $i['ini'] }}</span>@endif
                <span class="lp-t">{{ $i['t'] }}</span>@if($i['x'])<span class="lp-ext" aria-hidden="true">↗</span>@endif
            </a>
        @empty
            <span class="sub">Star ☆ a tool, account or brand to pin it here.</span>
        @endforelse
    </div>
    @if($launchpad['recent']->isNotEmpty())
        <div class="lp-row lp-recent">
            <span class="lp-h">Recent</span>
            @foreach($launchpad['recent'] as $i)
                <a href="{{ $i['u'] }}" data-lp="{{ $i['key'] }}" @if($i['x']) target="_blank" rel="noopener noreferrer" @endif title="{{ $i['s'] }}">{{ \App\Support\BrandIcon::html($i['ic'], 'xs') }}{{ $i['t'] }}</a>
            @endforeach
        </div>
    @endif
</section>
