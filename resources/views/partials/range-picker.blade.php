{{-- Date range and comparison for a report page. $range is an App\Support\DateRange; $route the page's route; $keep other filters to carry (e.g. brand); $presets limits the choices. --}}
@php($presetList = array_intersect_key(\App\Support\DateRange::PRESETS, array_flip($presets ?? array_keys(\App\Support\DateRange::PRESETS))))
<details class="rp-wrap">
<summary class="rp-sum">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
    <b>{{ $range->label() }}</b>@if($range->preset !== 'custom')<span class="rp-dates-t">{{ \App\Support\DateRange::span($range->from, $range->to) }}</span>@endif
    @if($range->compareLabel())<span class="rp-vs">compared with {{ $range->compareLabel() }}</span>@else<span class="rp-vs">no comparison</span>@endif
    <span class="rp-change">Change</span>
</summary>
<form class="range-picker" method="GET" action="{{ route($route) }}" data-range-picker aria-label="Date range and comparison">
    @foreach(($keep ?? []) as $k => $v)
        @if($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <label class="rp-f"><span>Dates</span>
        <select name="range" data-rp-preset>
            @foreach($presetList as $k => $label)
                <option value="{{ $k }}" @selected($range->preset === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <span class="rp-dates">
        <label class="rp-f"><span>From</span><input type="date" name="from" value="{{ $range->from->format('Y-m-d') }}" min="{{ $range->min->format('Y-m-d') }}" max="{{ $range->max->format('Y-m-d') }}" data-rp-date></label>
        <label class="rp-f"><span>To</span><input type="date" name="to" value="{{ $range->to->format('Y-m-d') }}" min="{{ $range->min->format('Y-m-d') }}" max="{{ $range->max->format('Y-m-d') }}" data-rp-date></label>
    </span>
    <label class="rp-f"><span>Compare with</span>
        <select name="compare" data-rp-compare>
            @foreach(\App\Support\DateRange::COMPARES as $k => $label)
                <option value="{{ $k }}" @selected($range->compare === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <button class="btn primary" type="submit">Apply</button>
    <p class="rp-foot sub">{{ number_format($range->days()) }} {{ $range->days() === 1 ? 'day' : 'days' }} · history from {{ $range->min->format('M j, Y') }}</p>
</form>
</details>
