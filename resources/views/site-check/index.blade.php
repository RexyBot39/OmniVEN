@extends('layouts.app', ['title' => 'Site check'])

@section('content')
<main class="wrap sales sitec">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Site check</span><span>{{ $last ? 'Checked '.\Carbon\Carbon::createFromTimestamp($last, config('app.timezone'))->diffForHumans() : 'Not checked yet' }} · daily</span></div>
            <h1 class="form-h">Site check @include('partials.star', ['key' => 'page:24', 'label' => 'Site check'])</h1>
            <p class="sub">Whether Google can show price, stock and stars for our pages, and whether the product feeds have problems.</p>
        </div>
        @can('admin')
            <form method="POST" action="{{ route('site-check.run') }}">@csrf<button class="btn" type="submit" @disabled($running)>{{ $running ? 'Checking…' : 'Check now' }}</button></form>
        @endcan
    </div>

    {{-- Product feeds --}}
    @if($feeds)
        <section>
            <div class="h2row"><h2>Product feeds</h2><span class="sub">What Google Shopping reads</span></div>
            <div class="sitec-feeds">
                @foreach($feeds as $slug => $f)
                    @php $r = $f['report']; $fails = collect($r['issues'] ?? [])->where('level', 'fail')->sum('count'); @endphp
                    <div class="card sitec-feed" style="--bc:{{ $f['brand'] ? 'var(--c'.$f['brand']->colorSlot().')' : 'var(--line)' }}">
                        <div class="h2row"><h3>{{ $f['brand']?->name ?? $slug }}</h3>@if($r && $r['items'] !== null)<span class="sub">{{ number_format($r['items']) }} products</span>@endif</div>
                        @if($r && $r['error'])
                            <p class="sitec-err" role="status"><b>{{ $r['items'] !== null ? 'The last check failed; these are older numbers.' : 'Couldn’t check the feed.' }}</b>@can('admin') {{ $r['error'] }}@endcan</p>
                        @endif
                        @if(! $r)
                            <p class="sub">Not checked yet.</p>
                        @elseif($r['items'] !== null)
                            @if(empty($r['issues']))
                                <p class="sitec-ok">✓ No problems found.</p>
                            @else
                                <p>@if($fails)<b class="sitec-bad">{{ number_format($fails) }} {{ $fails === 1 ? 'problem' : 'problems' }} Google rejects products for.</b> Fix the red ones first.@else No problems Google rejects products for; the rest make listings weaker.@endif</p>
                                <table class="sitec-t">
                                    <tbody>@foreach($r['issues'] as $i)<tr class="lv-{{ $i['level'] }}"><td><span class="sitec-dot" aria-hidden="true"></span>{{ $i['label'] }}<div class="sub">e.g. {{ implode(', ', $i['examples']) }}</div></td><td class="n">{{ number_format($i['count']) }}</td></tr>@endforeach</tbody>
                                </table>
                            @endif
                            @if(! empty($r['availability']))
                                <p class="sub">Stock: @foreach($r['availability'] as $a => $n){{ str_replace('_', ' ', $a) }} {{ number_format($n) }}@if(! $loop->last) · @endif @endforeach</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Structured data on pages --}}
    <section>
        <div class="h2row"><h2>Pages</h2>
            @if($pages->isNotEmpty())<span class="sub">{{ $counts['fail'] ?? 0 }} need fixing · {{ $counts['warn'] ?? 0 }} could be better · {{ $counts['ok'] ?? 0 }} fine @if($counts['error'] ?? 0)· {{ $counts['error'] }} couldn’t load @endif</span>@endif
        </div>
        @if($pages->isEmpty())
            <div class="empty">
                <p><b>No pages to check yet.</b> Add a few of each kind: the home page, best-selling product pages, a category page and a popular guide.</p>
                @can('admin')<form method="POST" action="{{ route('site-check.homes') }}">@csrf<button class="btn" type="submit">Add each brand’s home page</button></form>@endcan
            </div>
        @else
            <ul class="sitec-pages">
                @foreach($pages as $p)
                    <li class="card sitec-page st-{{ $p->status }}">
                        <div class="sitec-ph">
                            <span class="sitec-st">{{ ['fail' => 'Needs fixing', 'warn' => 'Could be better', 'ok' => 'Fine', 'error' => 'Couldn’t load', 'new' => 'Not checked'][$p->status] ?? $p->status }}</span>
                            <a class="sitec-url" href="{{ $p->url }}" target="_blank" rel="noopener noreferrer">{{ $p->path() }}</a>
                            <span class="sub">{{ $kinds[$p->kind] ?? $p->kind }}@if($p->brand) · {{ $p->brand->name }}@endif</span>
                        </div>
                        @if($p->error)<p class="sub">{{ $p->error }}</p>@endif
                        @if($p->issues)
                            <ul class="sitec-issues">@foreach($p->issues as [$lv, $text])<li class="lv-{{ $lv }}"><span class="sitec-dot" aria-hidden="true"></span>{{ $text }}</li>@endforeach</ul>
                        @endif
                        <div class="sitec-foot">
                            @if($p->found)<span class="sub">Found: {{ implode(', ', $p->found) }}</span>@endif
                            <a class="mini" href="{{ $p->richResultsUrl() }}" target="_blank" rel="noopener noreferrer">Google’s Rich Results Test ↗</a>
                            @if(in_array($p->status, ['fail', 'warn'], true))
                                <a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit('Fix the structured data on '.$p->path(), 190), 'details' => $p->url."\n\n".collect($p->issues)->filter(fn ($i) => $i[0] !== 'info')->map(fn ($i) => '- '.$i[1])->implode("\n")."\n\nCheck it with Google’s Rich Results Test after: ".$p->richResultsUrl()]) }}">Make a task</a>
                            @endif
                            @can('admin')
                                <form method="POST" action="{{ route('site-check.destroy', $p) }}" class="inline-f">@csrf @method('DELETE')<button class="mini linkish" type="submit">Remove</button></form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @can('admin')
            <form method="POST" action="{{ route('site-check.store') }}" class="card sitec-add">
                @csrf
                <label class="grow">Page address <input type="url" name="url" required maxlength="1024" placeholder="https://www.39dollarglasses.com/…" value="{{ old('url') }}"></label>
                <label>Kind <select name="kind">@foreach($kinds as $k => $label)<option value="{{ $k }}" @selected(old('kind', 'product') === $k)>{{ $label }}</option>@endforeach</select></label>
                <label>Brand <select name="brand_id"><option value="">—</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected((string) old('brand_id') === (string) $b->id)>{{ $b->name }}</option>@endforeach</select></label>
                <button class="btn primary" type="submit">Add page</button>
                @error('url')<p class="sitec-err">{{ $message }}</p>@enderror
            </form>
            @if($pages->isNotEmpty())<form method="POST" action="{{ route('site-check.homes') }}">@csrf<button class="mini linkish" type="submit">Add any missing brand home pages</button></form>@endif
        @endcan
    </section>

    @if(! $feeds)
        <section>
            <div class="h2row"><h2>Product feeds</h2></div>
            <div class="empty">
                <p>No product feeds connected.</p>
                @can('admin')<p class="sub">In Railway, set <code>PRODUCT_FEEDS</code> to each brand’s Google Shopping feed address (the one Merchant Center fetches), e.g. <code>39dg=https://www.39dollarglasses.com/feeds/google.xml</code>. The address must open without a login.</p>@endcan
            </div>
        </section>
    @endif
</main>
@endsection
