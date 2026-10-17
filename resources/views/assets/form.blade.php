@extends('layouts.app', ['title' => $asset->exists ? 'Edit '.$asset->title : 'Add an asset'])

@section('content')
<main class="wrap formpage">
    <a href="{{ route('assets.index') }}" class="back">← Affiliate assets</a>
    <h1 class="form-h">{{ $asset->exists ? 'Edit '.$asset->title : 'Add an asset' }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}" class="card form asset-form" enctype="multipart/form-data" id="asset-form">
        @csrf @if($asset->exists) @method('PUT') @endif
        <fieldset class="fld"><legend>Kind</legend>
            <div class="asset-kinds">@foreach(['image' => 'Banner or image', 'code' => 'Discount code', 'copy' => 'Copy', 'link' => 'Link'] as $k => $label)<label class="inline"><input type="radio" name="kind" value="{{ $k }}" @checked(old('kind', $asset->kind) === $k)> {{ $label }}</label>@endforeach</div>
        </fieldset>
        <div class="two">
            <div class="fld"><label for="title">Name</label><input id="title" name="title" type="text" required maxlength="160" value="{{ old('title', $asset->title) }}" placeholder="e.g. Holiday sale banner 300×250"></div>
            <div class="fld"><label for="brand_id">Brand</label><select id="brand_id" name="brand_id"><option value="">All brands</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected((string) old('brand_id', $asset->brand_id) === (string) $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        </div>
        <div class="fld" data-for="image">
            <label for="file">Image</label>
            @if($asset->sha1)<p class="sub"><img src="{{ route('assets.file', $asset) }}" alt="" class="asset-thumb"> {{ $asset->file_name }} · {{ $asset->width }}×{{ $asset->height }}. Choose a new file to replace it.</p>@endif
            <input id="file" name="file" type="file" accept="image/png,image/jpeg,image/gif,image/webp"><span class="hint">PNG, JPG, GIF or WebP, up to 5 MB.</span>
        </div>
        <div class="fld" data-for="code copy link image">
            <label for="body"><span data-for="code">The code</span><span data-for="copy">The text</span><span data-for="link image">Note <span class="sub">(optional)</span></span></label>
            <textarea id="body" name="body" rows="4" maxlength="5000" placeholder="">{{ old('body', $asset->body) }}</textarea>
        </div>
        <div class="fld" data-for="link"><label for="url">Link</label><input id="url" name="url" type="url" maxlength="1024" value="{{ old('url', $asset->url) }}" placeholder="https://… photo folder, landing page or brand guide"></div>
        <div class="fld" data-for="code"><label for="publisher_id">Only for one publisher</label><select id="publisher_id" name="publisher_id"><option value="">No, any publisher</option>@foreach($publishers as $p)<option value="{{ $p->id }}" @selected((string) old('publisher_id', $asset->publisher_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select><span class="hint">Their own code: it shows only in their kit and on their Outreach page.</span></div>
        <div class="two">
            <div class="fld"><label for="starts_on">From</label><input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', $asset->starts_on?->format('Y-m-d')) }}"></div>
            <div class="fld"><label for="ends_on">Until</label><input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $asset->ends_on?->format('Y-m-d')) }}"><span class="hint">Leave empty for evergreen. Ended assets drop out of kits.</span></div>
        </div>
        <label class="inline"><input type="checkbox" name="internal" value="1" @checked(old('internal', $asset->internal))> Staff only (never in a shared kit)</label>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ route('assets.index') }}">Cancel</a><button class="btn primary" type="submit">{{ $asset->exists ? 'Save' : 'Add' }}</button></div>
    </form>
    @if($asset->exists)@include('partials.delete', ['action' => route('assets.destroy', $asset), 'label' => 'Remove this asset'])@endif
</main>
@endsection
