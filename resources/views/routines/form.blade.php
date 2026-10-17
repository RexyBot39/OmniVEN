@extends('layouts.app', ['title' => $routine->exists ? 'Edit routine' : 'Add routine'])

@section('content')
<main class="wrap formpage">
    <a href="{{ route('dashboard') }}#routines" class="back">← Back to OmniVEN</a>
    <h1 class="form-h">{{ $routine->exists ? 'Edit routine' : 'Add a routine' }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $routine->exists ? route('routines.update', $routine) : route('routines.store') }}" class="card form">
        @csrf
        @if($routine->exists) @method('PUT') @endif
        <div class="fld"><label for="title">Task</label><input id="title" name="title" type="text" required maxlength="160" value="{{ old('title', $routine->title) }}"></div>
        <div class="two">
            <div class="fld"><label for="cadence">How often</label>
                <select id="cadence" name="cadence">@foreach(config('omniven.cadences') as $k => $v)<option value="{{ $k }}" @selected(old('cadence', $routine->cadence) === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <div class="fld"><label for="sort">Sort order</label><input id="sort" name="sort" type="number" min="0" max="9999" value="{{ old('sort', $routine->sort) }}"></div>
        </div>
        <div class="fld"><label for="assignee_id">Assigned to</label>
            <select id="assignee_id" name="assignee_id">
                <option value="">Whole team (anyone can tick it)</option>
                @foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('assignee_id', $routine->assignee_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <span class="hint">The person shows on the routine and on their staff page. Anyone can still tick it if they cover for them.</span>
        </div>
        <div class="fld"><label for="description">Details</label><textarea id="description" name="description" maxlength="1000">{{ old('description', $routine->description) }}</textarea></div>
        <div class="fld"><label for="link">Link to open</label><input id="link" name="link" type="url" placeholder="https://" maxlength="1024" value="{{ old('link', $routine->link) }}"></div>
        @include('partials.brand-checks', ['item' => $routine, 'perBrand' => true])
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ route('dashboard') }}#routines">Cancel</a><button class="btn primary" type="submit">Save routine</button></div>
    </form>
    @if($routine->exists)
        @include('partials.delete', ['action' => route('routines.destroy', $routine), 'label' => 'Delete this routine'])
    @endif
</main>
@endsection
