@extends('layouts.app', ['title' => ($brand?->name ?? '39DollarGlasses').' affiliate kit'])

@section('content')
<main class="wrap sales assets kit">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Affiliate kit</span>@if($publisher)<span>For {{ $publisher->name }}</span>@endif<span>Link works until {{ \Carbon\Carbon::createFromTimestamp($expires, config('app.timezone'))->format('M j, Y') }}</span></div>
            <h1 class="form-h">{{ $brand?->name ?? 'Our brands' }}: approved assets</h1>
            <p class="sub">Banners, codes and copy you may use when you feature us. Please use codes only before their end date, and don’t change the banners.</p>
        </div>
    </div>
    @php $total = $groups->sum(fn ($g) => $g->count()); @endphp
    @if($total === 0)
        <div class="empty">Nothing here right now. Please ask your contact for the latest assets.</div>
    @endif
    @foreach($groups as $kind => $items)
        @if($items->isNotEmpty())
            <section>
                <div class="h2row"><h2>{{ \App\Models\AffiliateAsset::KINDS[$kind] }}</h2></div>
                <ul class="asset-grid k-{{ $kind }}">
                    @foreach($items as $a)
                        @include('assets.card', ['a' => $a, 'img' => $kind === 'image' ? $fileUrl($a) : null, 'dl' => $kind === 'image' ? $fileUrl($a, true) : null, 'manage' => false, 'kit' => true])
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach
</main>
@endsection
