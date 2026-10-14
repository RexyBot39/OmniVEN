@extends('layouts.app', ['title' => 'Edit note'])

@section('content')
<main class="wrap formpage">
    <a href="{{ route('dashboard') }}" class="back">← Back to OmniVEN</a>
    <h1 class="form-h">Edit note</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ route('notes.update', $note) }}" class="card form">
        @csrf @method('PUT')
        <div class="fld"><label for="title">Headline</label><input id="title" name="title" type="text" required maxlength="160" value="{{ old('title', $note->title) }}"></div>
        <div class="fld"><label for="body">Details</label><textarea id="body" name="body" maxlength="4000" rows="6">{{ old('body', $note->body) }}</textarea></div>
        @can('admin')<label class="inline"><input type="checkbox" name="pinned" value="1" @checked(old('pinned', $note->pinned))> Pin to top</label>@endcan
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ route('dashboard') }}">Cancel</a><button class="btn primary" type="submit">Save note</button></div>
    </form>
    @include('partials.delete', ['action' => route('notes.destroy', $note), 'label' => 'Delete this note'])
</main>
@endsection
