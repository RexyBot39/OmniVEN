@extends('layouts.app', ['title' => $tool->exists ? 'Edit '.$tool->name : ($return === 'access' ? 'Add access item' : 'Add tool')])

@php($backUrl = $return === 'review' ? route('access.review') : route('dashboard').'#'.$return)
@section('content')
<main class="wrap formpage">
    <a href="{{ $backUrl }}" class="back">← Back to {{ ['access' => 'Access', 'review' => 'Access review'][$return] ?? 'Tools' }}</a>
    <h1 class="form-h">{{ $tool->exists ? 'Edit '.$tool->name : ($return === 'access' ? 'Add an access item' : 'Add a tool') }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $tool->exists ? route('tools.update', $tool) : route('tools.store') }}" class="card form">
        @csrf
        @if($tool->exists) @method('PUT') @endif
        <input type="hidden" name="return" value="{{ $return }}">
        @if($return === 'access')<p class="sub">Access items also appear in the Tools directory. Fill in the login account, owner and how to get access below.</p>@endif
        <div class="fld"><label for="name">Name</label><input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $tool->name) }}"></div>
        <div class="fld"><label for="url">Link</label><input id="url" name="url" type="url" placeholder="https://" maxlength="1024" value="{{ old('url', $tool->url) }}"></div>
        <div class="two">
            <div class="fld"><label for="category">Category</label>
                <input id="category" name="category" type="text" list="cats" maxlength="80" value="{{ old('category', $tool->category) }}">
                <datalist id="cats">@foreach(config('omniven.categories') as $c)<option value="{{ $c }}">@endforeach</datalist>
            </div>
            <div class="fld"><label for="status">Status</label>
                <select id="status" name="status">@foreach(config('omniven.statuses') as $k => $v)<option value="{{ $k }}" @selected(old('status', $tool->status) === $k)>{{ $v }}</option>@endforeach</select>
            </div>
        </div>
        <div class="fld"><label for="description">What it's for</label><textarea id="description" name="description" maxlength="1000">{{ old('description', $tool->description) }}</textarea></div>
        @include('partials.brand-checks', ['item' => $tool])
        <label class="inline"><input type="checkbox" name="featured" value="1" @checked(old('featured', $tool->featured))> Show in Platform consoles</label>
        <div class="two">
            <div class="fld"><label for="login">Login account</label><input id="login" name="login" type="text" maxlength="200" value="{{ old('login', $tool->login) }}"><span class="hint">The email or account it lives under. Never a password.</span></div>
            <div class="fld"><label for="owner">Owner / grants access</label><input id="owner" name="owner" type="text" maxlength="200" value="{{ old('owner', $tool->owner) }}"></div>
        </div>
        <div class="fld"><label for="access_note">How to get access</label><textarea id="access_note" name="access_note" maxlength="1000">{{ old('access_note', $tool->access_note) }}</textarea></div>
        <div class="fld narrow"><label for="sort">Sort order</label><input id="sort" name="sort" type="number" min="0" max="9999" value="{{ old('sort', $tool->sort) }}"><span class="hint">Lower numbers show first in their category.</span></div>
        <div class="form-actions">
            <span class="spacer"></span>
            <a class="btn" href="{{ $backUrl }}">Cancel</a>
            <button class="btn primary" type="submit">Save tool</button>
        </div>
    </form>
    @if($tool->exists)
        @include('partials.delete', ['action' => route('tools.destroy', [$tool, 'return' => $return]), 'label' => 'Delete this tool'])
    @endif
</main>
@endsection
