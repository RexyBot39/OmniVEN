{{-- ☆/★ toggle for My launchpad. Pass $item (tool, account or brand), or $key ("page:6", "category:123") with $label. --}}
@php
    $lpKey = isset($item) ? $item->launchKey() : $key;
    $lpKeys = $favKeys ?? \App\Support\Launchpad::myKeys();
    $lpOn = $lpKeys->has($lpKey);
    $lpName = $label ?? ($item->name ?? $item->title());
    [$lpType, $lpId] = explode(':', $lpKey);
@endphp
<form method="POST" action="{{ route('launchpad.favorite') }}" class="star-f" data-star="{{ $lpKey }}">
    @csrf
    <input type="hidden" name="type" value="{{ $lpType }}">
    <input type="hidden" name="id" value="{{ $lpId }}">
    <button type="submit" class="star" aria-pressed="{{ $lpOn ? 'true' : 'false' }}" title="{{ $lpOn ? 'Remove from' : 'Add to' }} My launchpad and Jump to" aria-label="Star {{ $lpName }}">{{ $lpOn ? '★' : '☆' }}</button>
</form>
