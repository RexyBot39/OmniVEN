{{-- A routine assigned to this person, shown in their column for the current day/week/month/quarter. --}}
@php($done = $r->isDone())
@php($slugs = $r->all_brands ? 'all' : $r->brands->pluck('slug')->implode(' '))
<div class="bt routine {{ $done ? 'done' : '' }}" data-brands="{{ $slugs ?: 'all' }}" data-search="{{ strtolower($r->title.' '.$r->description.' routine '.$r->cadence) }}">
    <form method="POST" action="{{ route('routines.toggle', $r) }}">@csrf
        <input type="hidden" name="return" value="tasks">
        <button class="tick" type="submit" aria-pressed="{{ $done ? 'true' : 'false' }}" aria-label="{{ $done ? 'Untick' : 'Tick' }} routine: {{ $r->title }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
    </form>
    <div class="bt-body">
        <div class="bt-t">@if($done)<s>{{ $r->title }}</s>@else{{ $r->title }}@endif</div>
        <div class="bt-m">
            <span class="rpill">↻ {{ config('omniven.cadences.'.$r->cadence) }} routine</span>
            @if($done)<span class="ok">Done {{ $r->cadence === 'daily' ? 'today' : 'this '.['weekly' => 'week', 'monthly' => 'month', 'quarterly' => 'quarter'][$r->cadence] ?? 'period' }}</span>@endif
            @if($r->link)<a href="{{ $r->link }}" target="_blank" rel="noopener noreferrer">Open ↗</a>@endif
        </div>
    </div>
</div>
