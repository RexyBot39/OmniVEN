@extends('layouts.app', ['title' => 'Campaign links'])

@section('content')
<main class="wrap links">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>UTM link builder</span><span>Everyone</span></div>
            <h1 class="form-h">Campaign links @include('partials.star', ['key' => 'page:15', 'label' => 'Campaign links'])</h1>
            <p class="sub links-why">Tag every link you post outside the website, so Sales can tell which posts, emails and partners brought in orders. Google Ads and Microsoft Ads tag themselves; don’t add these to them.</p>
        </div>
    </div>

    @if($made)
        <div class="made card" role="status">
            <div class="made-h"><b>Your link is ready.</b>@if($made->channelLabel())<span class="sub">Visits from it count as <b>{{ $made->channelLabel() }}</b> on the Sales page.</span>@endif</div>
            <div class="made-row">
                <input type="text" id="made-url" value="{{ $made->url }}" readonly aria-label="Tagged link">
                <button type="button" class="btn primary" data-copy="#made-url">Copy link</button>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('links.store') }}" class="card links-form" id="link-form">
        @csrf
        @include('partials.form-errors')
        <div class="links-grid">
            <div class="fld"><label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id">
                    <option value="">No brand</option>
                    @foreach($brands as $b)<option value="{{ $b->id }}" data-site="{{ $b->site }}" @selected((string) old('brand_id', $from?->brand_id ?? request('brand_id')) === (string) $b->id)>{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div class="fld span2"><label for="base_url">Page the link goes to</label>
                <input id="base_url" name="base_url" type="url" required maxlength="1024" placeholder="https://www.39dollarglasses.com/…" value="{{ old('base_url', $from?->base_url) }}">
            </div>
            <div class="fld"><label for="preset">Where it’s posted</label>
                <select id="preset" name="preset" required>
                    @foreach($presets as $group => $items)
                        <optgroup label="{{ $group }}">
                            @foreach($items as $key => [$label, $src, $med, $channel])
                                <option value="{{ $key }}" data-source="{{ $src }}" data-medium="{{ $med }}" data-channel="{{ $channel ? config('revenue.channels.'.$channel) : '' }}" @selected(old('preset', $from?->preset ?? 'instagram') === $key)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <span class="hint" id="preset-hint" aria-live="polite"></span>
            </div>
            <div class="fld" data-utm="source"><label for="source">Source <span class="hint">(the site or app, e.g. eyewearblog)</span></label>
                <input id="source" name="source" type="text" maxlength="100" list="past-sources" value="{{ old('source', $from?->source) }}">
                <datalist id="past-sources">@foreach($sources as $s)<option value="{{ $s }}">@endforeach</datalist>
            </div>
            <div class="fld" data-utm="medium"><label for="medium">Medium <span class="hint">(e.g. referral, display)</span></label>
                <input id="medium" name="medium" type="text" maxlength="100" value="{{ old('medium', $from?->medium) }}">
            </div>
            <div class="fld"><label for="campaign">Campaign</label>
                <input id="campaign" name="campaign" type="text" required maxlength="150" list="past-campaigns" placeholder="e.g. fall-sale-2026" value="{{ old('campaign', $from?->campaign ?? request('campaign')) }}">
                <datalist id="past-campaigns">@foreach($campaigns as $c)<option value="{{ $c }}">@endforeach</datalist>
                <span class="hint">Pick an existing name when it’s the same campaign.</span>
            </div>
            <div class="fld"><label for="content">Content <span class="hint">(optional: which post or creative)</span></label>
                <input id="content" name="content" type="text" maxlength="150" placeholder="e.g. reel-blue-light" value="{{ old('content', $from?->content) }}">
            </div>
            <div class="fld"><label for="term">Term <span class="hint">(optional: keyword)</span></label>
                <input id="term" name="term" type="text" maxlength="150" value="{{ old('term', $from?->term) }}">
            </div>
        </div>
        <p class="links-preview"><span class="sub">Preview</span> <code id="utm-preview">—</code></p>
        <div class="form-actions"><button class="btn primary" type="submit">Make link</button><span class="sub">Names are saved in lowercase with hyphens, so “Fall Sale” and “fall-sale” are the same campaign.</span></div>
    </form>

    <section>
        <div class="h2row">
            <h2>Links the team has made</h2><span class="sub">{{ $links->total() }} {{ Str::plural('link', $links->total()) }}</span>
            <form class="links-filter" method="GET" action="{{ route('links.index') }}" role="search">
                <select name="brand" aria-label="Brand"><option value="">All brands</option>@foreach($brands as $b)<option value="{{ $b->slug }}" @selected($brand?->is($b))>{{ $b->name }}</option>@endforeach</select>
                <input type="search" name="q" value="{{ $q }}" placeholder="Campaign, source or page…" aria-label="Search links">
                <button class="btn" type="submit">Find</button>
            </form>
        </div>
        @if($links->isEmpty())
            <div class="empty">{{ $q !== '' || $brand ? 'No links match.' : 'No links yet. Make the first one above.' }}</div>
        @else
            <div class="tablewrap">
                <table class="links-t">
                    <thead><tr><th>Campaign</th><th>Source / medium</th><th>Content</th><th>Link</th><th>Made</th><th><span class="sr">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach($links as $l)
                            <tr>
                                <td><b>{{ $l->campaign }}</b>@if($l->brand)<div class="sub"><span class="dot" style="background:var(--c{{ $l->brand->colorSlot() }})"></span>{{ $l->brand->name }}</div>@endif</td>
                                <td>{{ $l->source }} / {{ $l->medium }}@if($l->channelLabel())<div class="sub">{{ $l->channelLabel() }}</div>@endif</td>
                                <td>{{ $l->content ?? '—' }}@if($l->term)<div class="sub">term: {{ $l->term }}</div>@endif</td>
                                <td class="links-url"><input type="text" id="l-{{ $l->id }}" value="{{ $l->url }}" readonly aria-label="Link for {{ $l->campaign }}"></td>
                                <td class="sub">{{ $l->user?->firstName() ?? '—' }}<div>{{ $l->updated_at->timezone(config('app.timezone'))->format('M j, Y') }}</div></td>
                                <td class="row-actions">
                                    <button type="button" class="mini" data-copy="#l-{{ $l->id }}">Copy</button>
                                    <a class="mini" href="{{ route('links.index', ['copy' => $l->id]) }}#link-form">Make another like this</a>
                                    @if(auth()->user()->is_admin || $l->user_id === auth()->id())
                                        <form method="POST" action="{{ route('links.destroy', $l) }}" data-confirm>@csrf @method('DELETE')<button class="mini danger-link" type="submit" data-label="Remove">Remove</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($links->hasPages())
                <nav class="weeknav" aria-label="More links">
                    @if($links->previousPageUrl())<a class="btn" href="{{ $links->previousPageUrl() }}">← Newer</a>@endif
                    <span class="sub">Page {{ $links->currentPage() }} of {{ $links->lastPage() }}</span>
                    @if($links->nextPageUrl())<a class="btn" href="{{ $links->nextPageUrl() }}">Older →</a>@endif
                </nav>
            @endif
        @endif
    </section>
</main>
@endsection
