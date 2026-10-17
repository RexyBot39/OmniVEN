{{-- One asset. $img / $dl: image and download URLs (images only); $manage: show edit for admins. --}}
<li class="card asset-card k-{{ $a->kind }} st-{{ $a->state() }}">
    @if($a->kind === 'image')
        <a class="asset-img" href="{{ $img }}" target="_blank" rel="noopener"><img src="{{ $img }}" alt="{{ $a->title }}" loading="lazy" width="{{ $a->width }}" height="{{ $a->height }}"></a>
    @endif
    <div class="asset-b">
        <div class="asset-t"><b>{{ $a->title }}</b>
            @if($a->state() === 'ended')<span class="pill">Ended</span>@elseif($a->state() === 'upcoming')<span class="pill">From {{ $a->starts_on->format('M j') }}</span>@endif
            @if(! empty($manage) && $a->internal)<span class="pill">Staff only</span>@endif
        </div>
        @php
            $meta = array_filter([
                $a->kind === 'image' ? $a->width.'×'.$a->height.' · '.number_format($a->file_size / 1024).' KB · '.strtoupper(\App\Models\AffiliateAsset::MIMES[$a->file_mime] ?? '') : null,
                empty($kit) ? $a->brand?->name : null,
                $a->publisher ? 'Only for '.$a->publisher->name : null,
                $a->ends_on && $a->state() !== 'ended' ? 'Until '.$a->ends_on->format('M j, Y') : null,
            ]);
        @endphp
        @if($meta)<div class="sub">{{ implode(' · ', $meta) }}</div>@endif
        @if($a->kind === 'code')
            <div class="asset-copy"><input type="text" readonly id="as-{{ $a->id }}" value="{{ $a->body }}" class="asset-code" aria-label="Code: {{ $a->title }}"><button type="button" class="btn" data-copy="#as-{{ $a->id }}">Copy</button></div>
        @elseif($a->kind === 'copy')
            <textarea readonly id="as-{{ $a->id }}" rows="4" class="asset-text" aria-label="{{ $a->title }}">{{ $a->body }}</textarea>
            <button type="button" class="mini" data-copy="#as-{{ $a->id }}">Copy text</button>
        @elseif($a->kind === 'link')
            @if($a->body)<p class="asset-note">{{ $a->body }}</p>@endif
            <div class="asset-copy"><input type="text" readonly id="as-{{ $a->id }}" value="{{ $a->url }}" aria-label="Link: {{ $a->title }}"><a class="btn" href="{{ $a->url }}" target="_blank" rel="noopener noreferrer">Open ↗</a></div>
        @else
            @if($a->body)<p class="asset-note">{{ $a->body }}</p>@endif
            <a class="mini" href="{{ $dl }}">Download</a>
        @endif
        @if(! empty($manage))<a class="mini asset-edit" href="{{ route('assets.edit', $a) }}">Edit</a>@endif
    </div>
</li>
