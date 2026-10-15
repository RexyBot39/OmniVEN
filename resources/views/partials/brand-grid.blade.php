{{-- Brand × platform grid: one click to any brand's account; gaps show what's missing. --}}
<div class="grid-wrap tablewrap">
    <table class="bp-grid">
        <thead>
            <tr>
                <th scope="col">Brand</th>
                @foreach($gridPlatforms as $key => $p)<th scope="col" title="{{ $p['label'] }}"><span class="th-full">{{ $p['label'] }}</span></th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($brands as $b)
                <tr data-brand-row="{{ $b->slug }}">
                    <th scope="row"><a href="{{ route('brands.show', $b) }}"><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a></th>
                    @foreach($gridPlatforms as $key => $p)
                        @php($a = $accountGrid[$b->id][$key] ?? null)
                        <td>
                            @if($a)
                                <a class="cell-link {{ $a->hasDirectLink() ? '' : 'weak' }} {{ $a->needsVerify() ? 'stale' : '' }}" href="{{ $a->openUrl() }}" target="_blank" rel="noopener noreferrer"
                                   title="{{ $a->title() }}{{ $a->hasDirectLink() ? '' : ' (no direct link yet)' }}{{ $a->needsVerify() ? ' · access not verified recently' : '' }}">
                                    <span class="plat" data-p="{{ $key }}">{{ $p['short'] }}</span>
                                </a>
                            @elseif($isAdmin)
                                <a class="cell-add" href="{{ route('accounts.create', ['brand' => $b->slug, 'platform' => $key, 'return' => 'brands']) }}" title="Add {{ $b->name }} {{ $p['label'] }}" aria-label="Add {{ $b->name }} {{ $p['label'] }}">+</a>
                            @else
                                <span class="cell-none" aria-label="Not set up">·</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="legend sub">Click a square to open that account. Faded squares have no direct link yet; a dot in the corner means access hasn't been verified in 90 days.{{ $isAdmin ? ' + adds a missing account.' : '' }}</p>
