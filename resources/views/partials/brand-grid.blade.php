{{-- Brand × platform grid: one click to any brand's account; gaps show what's missing. --}}
<div class="grid-wrap tablewrap">
    <table class="bp-grid">
        <thead>
            <tr>
                <th scope="col">Brand</th>
                @foreach($gridPlatforms as $key => $p)<th scope="col" title="{{ $p['label'] }}"><span class="th-full">{{ \App\Support\BrandIcon::html(\App\Support\BrandIcon::forPlatform($key), 'xs') }}{{ $p['label'] }}</span></th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($brands as $b)
                @php($srcId = $b->accounts_from_id ?: $b->id)
                @php($srcName = $b->accounts_from_id ? $brands->firstWhere('id', $b->accounts_from_id)?->name : null)
                <tr data-brand-row="{{ $b->slug }}" @class(['shared-row' => $srcName])>
                    <th scope="row"><a href="{{ route('brands.show', $b) }}"><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>@if($srcName)<small class="shared-note">uses {{ $srcName }}</small>@endif</th>
                    @foreach($gridPlatforms as $key => $p)
                        @php($a = $accountGrid[$srcId][$key] ?? null)
                        <td>
                            @if($a)
                                @php($aBroken = isset($brokenLinks) && $brokenLinks->has('account:'.$a->id))
                                <a class="cell-link {{ $a->hasDirectLink() ? '' : 'weak' }} {{ $a->needsVerify() ? 'stale' : '' }} {{ $aBroken ? 'broken' : '' }}" href="{{ $a->openUrl() }}" target="_blank" rel="noopener noreferrer"
                                   title="{{ $a->title() }}{{ $a->hasDirectLink() ? '' : ' (no direct link yet)' }}{{ $a->needsVerify() ? ' · access not verified recently' : '' }}{{ $aBroken ? ' · link broken' : '' }}">
                                    @php($ic = \App\Support\BrandIcon::forPlatform($key))@if($ic){{ \App\Support\BrandIcon::html($ic, 'md', $a->title()) }}@else<span class="plat" data-p="{{ $key }}">{{ $p['short'] }}</span>@endif
                                </a>
                            @elseif($isAdmin && ! $srcName)
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
<p class="legend sub">Click a square to open that account. Faded squares have no direct link yet; a dot in the corner means access hasn't been verified in 90 days.{{ $isAdmin ? ' + adds a missing account.' : '' }} Brands marked “uses …” share that brand's accounts.</p>
