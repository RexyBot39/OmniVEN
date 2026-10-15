@extends('layouts.app', ['title' => ($item->exists ? 'Edit' : 'Add').' calendar item'])

@php($back = route('calendar.week', $week).($item->exists ? '#item-'.$item->id : '#week'))

@section('content')
<main class="wrap formpage">
    <a href="{{ $back }}" class="back">← Week {{ $week->number }} · {{ $week->range_label }}</a>
    <h1 class="form-h">{{ $item->exists ? 'Edit calendar item' : 'Add a calendar item' }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $item->exists ? route('calendar.items.update', [$week, $item]) : route('calendar.items.store', $week) }}" class="card form">
        @csrf
        @if($item->exists) @method('PUT') @endif
        <div class="fld"><label for="title">What to do</label><textarea id="title" name="title" required maxlength="1000" rows="3">{{ old('title', $item->title) }}</textarea>
            <span class="hint">Name the platforms (Instagram, TikTok, Pinterest, LinkedIn…) and their logos appear next to the item.</span></div>
        <fieldset class="fld scope">
            <legend>Shows in</legend>
            <label class="inline"><input type="radio" name="scope" value="week" @checked(old('scope', $scope) === 'week')> Week {{ $item->week?->number ?? $week->number }} only ({{ $item->week?->range_label ?? $week->range_label }})</label>
            <label class="inline"><input type="radio" name="scope" value="every" @checked(old('scope', $scope) === 'every')> Every week (all {{ $weekCount }})</label>
            @if($item->exists && ! $item->calendar_week_id)
                <span class="hint warn-t">This item repeats every week. Changes apply to all {{ $weekCount }} weeks. Switching it to one week removes its checkmarks from the other weeks.</span>
            @endif
        </fieldset>
        <div class="two">
            <div class="fld"><label for="day">Day</label>
                <select id="day" name="day">
                    @foreach(\App\Models\CalendarWeek::DAY_ORDER as $d)<option value="{{ $d }}" @selected(old('day', $item->day) === $d)>{{ ['Setup' => 'One-off this week', 'Review' => 'Review milestone'][$d] ?? $d }}</option>@endforeach
                </select>
            </div>
            <div class="fld"><label for="brand_slug">Brand</label>
                <select id="brand_slug" name="brand_slug">
                    <option value="">All brands</option>
                    @foreach($brands as $b)<option value="{{ $b->slug }}" @selected(old('brand_slug', $item->brand_slug) === $b->slug)>{{ $b->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="two">
            <div class="fld"><label for="owner">Owner</label>
                <input id="owner" name="owner" list="owners" required maxlength="40" value="{{ old('owner', $item->owner) }}">
                <datalist id="owners">@foreach(\App\Http\Controllers\CalendarItemController::OWNERS as $o)<option value="{{ $o }}">@endforeach</datalist>
                <span class="hint">“Marc” shows as “Marc signs off”.</span>
            </div>
            <div class="fld"><label for="keyword">Funnel keyword <span class="hint">(optional)</span></label><input id="keyword" name="keyword" maxlength="60" placeholder="GUIDE, FOG / Z87…" value="{{ old('keyword', $item->keyword) }}"></div>
        </div>
        <div class="fld"><label for="done_when">Done when <span class="hint">(optional)</span></label><textarea id="done_when" name="done_when" maxlength="1000" rows="2">{{ old('done_when', $item->done_when) }}</textarea></div>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ $back }}">Cancel</a><button class="btn primary" type="submit">{{ $item->exists ? 'Save item' : 'Add item' }}</button></div>
    </form>
    @if($item->exists)
        <p class="sub">{{ $item->calendar_week_id ? 'Deleting removes it from week '.$item->week->number : 'Deleting removes it from all '.$weekCount.' weeks' }}{{ $checkCount ? ', along with '.$checkCount.' '.\Illuminate\Support\Str::plural('checkmark', $checkCount) : '' }}.</p>
        @include('partials.delete', ['action' => route('calendar.items.destroy', [$week, $item]), 'label' => 'Delete this item'])
    @endif
</main>
@endsection
