@extends('layouts.app', ['title' => $campaign->exists ? 'Edit '.$campaign->name : 'Plan a campaign'])

@section('content')
<main class="wrap formpage">
    <a href="{{ $campaign->exists ? route('campaigns.show', $campaign) : route('campaigns.index') }}" class="back">← {{ $campaign->exists ? $campaign->name : 'Campaigns' }}</a>
    <h1 class="form-h">{{ $campaign->exists ? 'Edit '.$campaign->name : 'Plan a campaign' }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $campaign->exists ? route('campaigns.update', $campaign) : route('campaigns.store') }}" class="card form">
        @csrf @if($campaign->exists) @method('PUT') @endif
        <div class="two">
            <div class="fld"><label for="name">Name</label><input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $campaign->name) }}" placeholder="e.g. Black Friday 2026"></div>
            <div class="fld"><label for="tag">Link tag <span class="hint">(utm_campaign)</span></label><input id="tag" name="tag" type="text" maxlength="150" value="{{ old('tag', $campaign->tag) }}" placeholder="Made from the name, e.g. black-friday-2026"><span class="hint">Campaign links with this tag belong to the campaign.</span></div>
        </div>
        <div class="two">
            <div class="fld"><label for="starts_on">Starts</label><input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on', $campaign->starts_on?->format('Y-m-d')) }}"></div>
            <div class="fld"><label for="ends_on">Ends</label><input id="ends_on" name="ends_on" type="date" required value="{{ old('ends_on', $campaign->ends_on?->format('Y-m-d')) }}"></div>
        </div>
        <div class="two">
            <div class="fld"><label for="brand_id">Brand</label><select id="brand_id" name="brand_id"><option value="">All brands</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected((string) old('brand_id', $campaign->brand_id) === (string) $b->id)>{{ $b->name }}</option>@endforeach</select></div>
            <div class="fld"><label for="owner_id">Owner</label><select id="owner_id" name="owner_id"><option value="">Nobody yet</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('owner_id', $campaign->owner_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        </div>
        <div class="fld"><label for="goal">Goal</label><input id="goal" name="goal" type="text" maxlength="300" value="{{ old('goal', $campaign->goal) }}" placeholder="e.g. 1,200 orders, 15% off frames, email + Meta + affiliates"></div>
        <div class="fld"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="4" maxlength="4000">{{ old('notes', $campaign->notes) }}</textarea></div>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ $campaign->exists ? route('campaigns.show', $campaign) : route('campaigns.index') }}">Cancel</a><button class="btn primary" type="submit">{{ $campaign->exists ? 'Save' : 'Add campaign' }}</button></div>
    </form>
    @if($campaign->exists)@can('admin')@include('partials.delete', ['action' => route('campaigns.destroy', $campaign), 'label' => 'Remove campaign'])@endcan @endif
</main>
@endsection
