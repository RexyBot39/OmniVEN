@extends('layouts.app', ['title' => $brand->exists ? 'Edit '.$brand->name : 'Add brand'])

@section('content')
<main class="wrap formpage">
    <a href="{{ route('dashboard') }}#brands" class="back">← Back to OmniVEN</a>
    <h1 class="form-h">{{ $brand->exists ? 'Edit '.$brand->name : 'Add a brand' }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $brand->exists ? route('brands.update', $brand) : route('brands.store') }}" class="card form">
        @csrf
        @if($brand->exists) @method('PUT') @endif
        <div class="fld"><label for="name">Brand name</label><input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $brand->name) }}"></div>
        <div class="fld"><label for="site">Website</label><input id="site" name="site" type="url" placeholder="https://" maxlength="1024" value="{{ old('site', $brand->site) }}"></div>
        <div class="fld"><label for="focus">Focus</label><textarea id="focus" name="focus" maxlength="1000">{{ old('focus', $brand->focus) }}</textarea></div>
        <div class="fld"><label for="channels">Main channels</label><input id="channels" name="channels" type="text" maxlength="1000" value="{{ old('channels', $brand->channels) }}"></div>
        <div class="fld"><label for="socials_text">Social accounts</label>
            <textarea id="socials_text" name="socials_text" placeholder="Instagram | https://instagram.com/yourbrand">{{ old('socials_text', collect($brand->socials ?? [])->map(fn ($s) => trim(($s['label'] ?? '').' | '.($s['url'] ?? ''), ' |'))->implode("\n")) }}</textarea>
            <span class="hint">One per line: Label | https://link</span>
        </div>
        <div class="fld"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes', $brand->notes) }}</textarea></div>
        <div class="fld narrow"><label for="sort">Sort order</label><input id="sort" name="sort" type="number" min="0" max="9999" value="{{ old('sort', $brand->sort) }}"></div>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ route('dashboard') }}#brands">Cancel</a><button class="btn primary" type="submit">Save brand</button></div>
    </form>
    @if($brand->exists)
        @include('partials.delete', ['action' => route('brands.destroy', $brand), 'label' => 'Delete this brand'])
    @endif
</main>
@endsection
