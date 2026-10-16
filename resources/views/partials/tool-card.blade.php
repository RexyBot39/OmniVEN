{{-- One tool in the directory. Kept to name, purpose and anything unusual (a status other than Active, a
     brand limit, a broken link). Logins and owners live on the Access tab. --}}
<article class="tool" data-brands="{{ $t->brandFilterAttr() }}" data-search="{{ strtolower($t->name.' '.$t->description.' '.$t->category.' '.$t->login.' '.$t->owner.' '.$t->access_note) }}">
    @php($ic = \App\Support\BrandIcon::forTool($t))
    @if($ic){{ \App\Support\BrandIcon::html($ic, 'lg', $t->name) }}@else<span class="mono" style="--c:var(--c{{ $t->categorySlot() }})">{{ $t->initials() }}</span>@endif
    <div class="t">
        @if($t->url)
            <a href="{{ $t->url }}" target="_blank" rel="noopener noreferrer" data-lp="{{ $t->launchKey() }}">{{ $t->name }} ↗</a>
        @else
            <strong>{{ $t->name }}</strong>
        @endif
        @if($t->status !== 'active')<span class="pill {{ $t->status }}">{{ $t->statusLabel() }}</span>@endif
        @if(isset($brokenLinks) && $brokenLinks->has('tool:'.$t->id))<span class="pill link-broken" title="This link failed the last two nightly checks">Link broken</span>@endif
    </div>
    <div class="d">{{ $t->description }}</div>
    @unless($t->all_brands)<div class="foot">@foreach($t->brandLabels() as $label)<span class="tag">{{ $label }}</span>@endforeach</div>@endunless
    <span class="acts">
        @include('partials.star', ['item' => $t])
        @can('admin')<a class="mini" href="{{ route('tools.edit', $t) }}">Edit</a>@endcan
    </span>
</article>
