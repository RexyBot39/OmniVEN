{{-- ☆/★ toggle for My launchpad. Needs $item (tool, account or brand); $favKeys comes from the page. --}}
@php
    $lpKey = $item->launchKey();
    $lpOn = isset($favKeys) && $favKeys->has($lpKey);
    $lpName = $label ?? ($item->name ?? $item->title());
@endphp
<form method="POST" action="{{ route('launchpad.favorite') }}" class="star-f" data-star="{{ $lpKey }}">
    @csrf
    <input type="hidden" name="type" value="{{ explode(':', $lpKey)[0] }}">
    <input type="hidden" name="id" value="{{ $item->getKey() }}">
    <button type="submit" class="star" aria-pressed="{{ $lpOn ? 'true' : 'false' }}" title="{{ $lpOn ? 'Remove from' : 'Add to' }} My launchpad" aria-label="Star {{ $lpName }}">{{ $lpOn ? '★' : '☆' }}</button>
</form>
