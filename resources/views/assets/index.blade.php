@extends('layouts.app', ['title' => 'Affiliate assets'])

@section('content')
<main class="wrap sales assets">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Affiliate assets</span><span>Approved for publishers</span></div>
            <h1 class="form-h">Affiliate assets @include('partials.star', ['key' => 'page:27', 'label' => 'Affiliate assets'])</h1>
            <p class="sub">Banners, discount codes, copy and links that affiliates, review sites and creators may use. Copy what you need, or share a kit link.</p>
        </div>
        @can('admin')<a class="btn primary" href="{{ route('assets.create') }}">+ Add an asset</a>@endcan
    </div>

    @if($brands->count() > 1)
        <nav class="sales-brands" aria-label="Brand">
            <a href="{{ route('assets.index') }}" @if(! $brand) aria-current="true" @endif>All brands</a>
            @foreach($brands as $b)<a href="{{ route('assets.index', ['brand' => $b->slug]) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>@endforeach
        </nav>
    @endif

    @can('admin')
        @if(session('kit_link'))
            <div class="card asset-share-made" role="status">
                <b>Send this link to the publisher.</b> Anyone with it can see the kit until it expires, so send it only to them.
                <div class="asset-copy"><input type="text" readonly id="kit-link" value="{{ session('kit_link') }}" aria-label="Share link"><button type="button" class="btn primary" data-copy="#kit-link">Copy link</button></div>
            </div>
        @endif
        <details class="card asset-share">
            <summary><b>Share a kit with a publisher</b> <span class="sub">A page with these assets they can open without signing in</span></summary>
            <form method="POST" action="{{ route('assets.share') }}" class="asset-share-f">
                @csrf
                <label>Brand <select name="brand_id"><option value="">All brands</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected($brand?->is($b))>{{ $b->name }}</option>@endforeach</select></label>
                <label>Publisher <select name="publisher_id"><option value="">Anyone (no publisher-only codes)</option>@foreach($publishers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
                <label>Works for <select name="days"><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option></select></label>
                <button class="btn" type="submit">Make link</button>
            </form>
            <p class="sub">Kits leave out staff-only assets, ended codes, and codes that belong to another publisher.</p>
        </details>
    @endcan

    @php $total = $groups->sum(fn ($g) => $g->count()); @endphp
    @if($total === 0)
        <div class="empty">No assets yet.@can('admin') Add the current banners, the evergreen discount codes, a short and a long brand description, and a link to the product photo folder.@endcan</div>
    @endif

    @foreach($groups as $kind => $items)
        @if($items->isNotEmpty())
            <section>
                <div class="h2row"><h2>{{ \App\Models\AffiliateAsset::KINDS[$kind] }}</h2><span class="sub">{{ $items->count() }}</span>@can('admin')<a class="mini" href="{{ route('assets.create', ['kind' => $kind]) }}">+ Add</a>@endcan</div>
                <ul class="asset-grid k-{{ $kind }}">
                    @foreach($items as $a)
                        @include('assets.card', ['a' => $a, 'img' => route('assets.file', $a), 'dl' => route('assets.file', [$a, 'download' => 1]), 'manage' => auth()->user()->can('admin')])
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if($endedCount)
        <p class="sub"><a href="{{ route('assets.index', array_filter(['brand' => $brand?->slug, 'ended' => $showEnded ? null : 1])) }}">{{ $showEnded ? 'Hide' : 'Show' }} {{ $endedCount }} ended {{ Str::plural('asset', $endedCount) }}</a></p>
    @endif
</main>
@endsection
