@extends('layouts.app', ['title' => $publisher->exists ? 'Edit '.$publisher->name : 'Add a publisher'])

@section('content')
<main class="wrap formpage">
    <a href="{{ $publisher->exists ? route('outreach.show', $publisher) : route('outreach.index') }}" class="back">← {{ $publisher->exists ? $publisher->name : 'Outreach' }}</a>
    <h1 class="form-h">{{ $publisher->exists ? 'Edit '.$publisher->name : 'Add a publisher' }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $publisher->exists ? route('outreach.update', $publisher) : route('outreach.store') }}" class="card form">
        @csrf @if($publisher->exists) @method('PUT') @endif
        <div class="two">
            <div class="fld"><label for="name">Name</label><input id="name" name="name" type="text" required maxlength="160" value="{{ old('name', $publisher->name) }}" placeholder="e.g. All About Vision"></div>
            <div class="fld"><label for="site">Website</label><input id="site" name="site" type="url" maxlength="500" value="{{ old('site', $publisher->site) }}" placeholder="https://…"></div>
        </div>
        <div class="two">
            <div class="fld"><label for="type">Kind</label><select id="type" name="type">@foreach($types as $k => $label)<option value="{{ $k }}" @selected(old('type', $publisher->type) === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="fld"><label for="brand_id">For brand</label><select id="brand_id" name="brand_id"><option value="">Any</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected((string) old('brand_id', $publisher->brand_id) === (string) $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        </div>
        <div class="two">
            <div class="fld"><label for="owner_id">Owner</label><select id="owner_id" name="owner_id"><option value="">Nobody yet</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('owner_id', $publisher->owner_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select></div>
            <div class="fld"><label for="stage">Stage</label><select id="stage" name="stage">@foreach($stages as $k => $label)<option value="{{ $k }}" @selected(old('stage', $publisher->stage) === $k)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div class="two">
            <div class="fld"><label for="contact">Contact</label><input id="contact" name="contact" type="text" maxlength="200" value="{{ old('contact', $publisher->contact) }}" placeholder="Name, email or contact form link"><span class="hint">Their business contact only.</span></div>
            <div class="fld"><label for="follow_up_on">Follow up on</label><input id="follow_up_on" name="follow_up_on" type="date" value="{{ old('follow_up_on', $publisher->follow_up_on?->format('Y-m-d')) }}"></div>
        </div>
        <div class="two">
            <div class="fld"><label for="network">Affiliate network</label><select id="network" name="network"><option value="">None</option>@foreach($networks as $k => $label)<option value="{{ $k }}" @selected(old('network', $publisher->network) === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="fld"><label for="network_id">Their publisher ID there</label><input id="network_id" name="network_id" type="text" maxlength="64" value="{{ old('network_id', $publisher->network_id) }}"><span class="hint">Shows their affiliate sales here.</span></div>
        </div>
        <div class="fld"><label for="result_url">Live link</label><input id="result_url" name="result_url" type="url" maxlength="1024" value="{{ old('result_url', $publisher->result_url) }}" placeholder="The article, review or post that mentions us"></div>
        <div class="fld"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="4" maxlength="4000" placeholder="What they cover, audience, rates, what we offered">{{ old('notes', $publisher->notes) }}</textarea></div>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ $publisher->exists ? route('outreach.show', $publisher) : route('outreach.index') }}">Cancel</a><button class="btn primary" type="submit">{{ $publisher->exists ? 'Save' : 'Add' }}</button></div>
    </form>

    @unless($publisher->exists)
        <h2 class="form-h">Or paste a list</h2>
        <form method="POST" action="{{ route('outreach.bulk') }}" class="card form">
            @csrf
            <div class="fld"><label for="lines">One per line: name, website</label><textarea id="lines" name="lines" rows="6" maxlength="20000" placeholder="All About Vision, https://www.allaboutvision.com&#10;https://www.glassescompare.com">{{ old('lines') }}</textarea><span class="hint">A bare web address works too. Ones already on the list are skipped. You own the ones you add.</span></div>
            <div class="two">
                <div class="fld"><label for="bulk-type">Kind</label><select id="bulk-type" name="type">@foreach($types as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                <div class="fld"><label for="bulk-brand">For brand</label><select id="bulk-brand" name="brand_id"><option value="">Any</option>@foreach($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
            </div>
            <div class="form-actions"><span class="spacer"></span><button class="btn primary" type="submit">Add all</button></div>
        </form>
    @endunless

    @if($publisher->exists)
        @can('admin')@include('partials.delete', ['action' => route('outreach.destroy', $publisher), 'label' => 'Remove from outreach'])@endcan
    @endif
</main>
@endsection
