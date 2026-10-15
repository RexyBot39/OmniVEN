<article class="tool" data-brands="{{ $t->brandFilterAttr() }}" data-search="{{ strtolower($t->name.' '.$t->description.' '.$t->category.' '.$t->login.' '.$t->owner.' '.$t->access_note) }}">
    <span class="mono" style="--c:var(--c{{ $t->categorySlot() }})">{{ $t->initials() }}</span>
    <div class="t">
        @if($t->url)
            <a href="{{ $t->url }}" target="_blank" rel="noopener noreferrer" data-lp="{{ $t->launchKey() }}">{{ $t->name }} ↗</a>
        @else
            <strong>{{ $t->name }}</strong>
        @endif
        <span class="pill {{ $t->status }}">{{ $t->statusLabel() }}</span>
        @if(isset($brokenLinks) && $brokenLinks->has('tool:'.$t->id))<span class="pill link-broken" title="This link failed the last two nightly checks">Link broken</span>@endif
    </div>
    <div class="d">{{ $t->description }}</div>
    @if($t->login || $t->owner || $t->access_note)
        <div class="acct">
            @if($t->login)Login: <b>{{ $t->login }}</b>@endif
            @if($t->login && $t->owner) · @endif
            @if($t->owner)Owner: <b>{{ $t->owner }}</b>@endif
            @if($t->access_note)<div>{{ $t->access_note }}</div>@endif
        </div>
    @endif
    <div class="foot">
        @foreach($t->brandLabels() as $label)<span class="tag">{{ $label }}</span>@endforeach
        <span class="spacer"></span>
        @include('partials.star', ['item' => $t])
        @can('admin')<a class="mini" href="{{ route('tools.edit', $t) }}">Edit</a>@endcan
    </div>
</article>
