@extends('layouts.app')

@php
    $isAdmin = auth()->user()->can('admin');
    $due = $routines->reject->isDone()->count();
    $me = auth()->user();
    $myOpen = $openTasks->where('assignee_id', $me->id)->count();
@endphp

@section('content')
<section class="hero wrap">
    <div class="eyebrow">
        <span>Marketing control center</span>
        <span><b>{{ $brands->count() }}</b> brands</span>
        <span><b>{{ $tools->count() }}</b> tools</span>
        <span><b>{{ $due }}</b> routines due</span>
        <span>{{ now()->format('D, M j, Y') }}</span>
    </div>
    <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->firstName() }}.</h1>
</section>

@include('partials.hotlist')
@include('partials.launchpad')

<div class="bar">
    <div class="wrap bar-in">
        <div class="row1">
            <div class="tabs" role="tablist">
                <button class="tab" role="tab" data-tab="routines" aria-selected="true">Routines<span class="n">{{ $due }} due</span></button>
                <button class="tab" role="tab" data-tab="tasks" aria-selected="false">Tasks<span class="n">{{ $myOpen ? $myOpen.' yours' : $openTasks->count() }}</span></button>
                <button class="tab" role="tab" data-tab="postit" aria-selected="false">PostIT Notes</button>
                <button class="tab" role="tab" data-tab="slack" aria-selected="false">Slack<span class="unread-star" id="slack-star" hidden aria-label="new Slack messages">*</span></button>
                <button class="tab" role="tab" data-tab="brands" aria-selected="false">Brands<span class="n">{{ $brands->count() }}</span></button>
                <button class="tab" role="tab" data-tab="tools" aria-selected="false">Tools<span class="n">{{ $tools->count() }}</span></button>
                <button class="tab" role="tab" data-tab="access" aria-selected="false">Access @if($openRequests->isNotEmpty())<span class="n req-n">{{ $openRequests->count() }} {{ Str::plural('request', $openRequests->count()) }}</span>@endif</button>
            </div>
            <label class="search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="q" type="search" placeholder="Search tools, logins, routines…" autocomplete="off" aria-label="Search">
            </label>
        </div>
        <div class="chips" aria-label="Filter by brand">
            <button class="chip" data-brand="all" aria-pressed="true">All brands</button>
            @foreach($brands as $b)
                <button class="chip" data-brand="{{ $b->slug }}" aria-pressed="false"><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</button>
            @endforeach
        </div>
    </div>
</div>

<main class="wrap main">

{{-- ================= TOOLS ================= --}}
<div data-view="tools" hidden>
    <section>
        <div class="h2row">
            <h2>Bulletin</h2>
            <button class="btn ghost" type="button" data-toggle="note-form">+ Post a note</button>
        </div>
        <form method="POST" action="{{ route('notes.store') }}" class="note-form card" id="note-form" hidden>
            @csrf
            <div class="fld"><label for="n-title">Headline</label><input id="n-title" name="title" type="text" required maxlength="160"></div>
            <div class="fld"><label for="n-body">Details</label><textarea id="n-body" name="body" maxlength="4000"></textarea></div>
            <div class="form-actions">
                @if($isAdmin)<label class="inline"><input type="checkbox" name="pinned" value="1"> Pin to top</label>@endif
                <span class="spacer"></span>
                <button class="btn" type="button" data-toggle="note-form">Cancel</button>
                <button class="btn primary" type="submit">Post</button>
            </div>
        </form>
        @if($notes->isEmpty())
            <div class="empty">No notes yet. Post the first announcement or campaign update.</div>
        @else
            <div class="notes">
                @foreach($notes as $n)
                    <article class="note {{ $n->pinned ? 'pinned' : '' }}">
                        <div class="meta">
                            <span>{{ $n->pinned ? 'Pinned · ' : '' }}{{ $n->created_at->format('M j') }}{{ $n->user ? ' · '.$n->user->firstName() : '' }}</span>
                            @can('manage-note', $n)<a href="{{ route('notes.edit', $n) }}" class="mini">Edit</a>@endcan
                        </div>
                        <h3>{{ $n->title }}</h3>
                        @if($n->body)<p>{{ $n->body }}</p>@endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <div class="h2row"><h2>Platform consoles</h2><span class="sub">The ad, shopping and search accounts we open most</span></div>
        <div class="consoles">
            @forelse($tools->where('featured', true) as $t)
                <a class="console" href="{{ $t->url }}" target="_blank" rel="noopener noreferrer" data-lp="{{ $t->launchKey() }}" data-brands="{{ $t->brandFilterAttr() }}" data-search="{{ strtolower($t->name.' '.$t->description.' '.$t->category) }}">
                    <span class="mono" style="--c:var(--c{{ $t->categorySlot() }})">{{ $t->initials() }}</span>
                    <span class="name">{{ $t->name }}</span>
                    <span class="d">{{ $t->description }}</span>
                    <span class="go">{{ $t->host() }} ↗</span>
                </a>
            @empty
                <div class="empty">No consoles pinned yet. Admins can tick “Show in Platform consoles” on any tool.</div>
            @endforelse
        </div>
    </section>

    <section>
        <div class="h2row">
            <h2>Tool directory</h2>
            @if($isAdmin)<a class="btn primary" href="{{ route('tools.create') }}">+ Add tool</a>@endif
        </div>
        <div class="empty filter-empty" hidden>No tools match. Clear the search or pick All brands.</div>
        <div class="dir">
            @foreach($categories as $cat)
                <div class="cat" data-group>
                    <div class="cat-h"><span class="sq" style="background:var(--c{{ $cat['tools']->first()->categorySlot() }})"></span>{{ $cat['name'] }}</div>
                    <div class="grid">
                        @foreach($cat['tools'] as $t) @include('partials.tool-card', ['t' => $t]) @endforeach
                    </div>
                </div>
            @endforeach
            @if($uncategorized->isNotEmpty())
                <div class="cat" data-group><div class="cat-h">Other</div><div class="grid">@foreach($uncategorized as $t) @include('partials.tool-card', ['t' => $t]) @endforeach</div></div>
            @endif
        </div>
    </section>
</div>

{{-- ================= BRANDS ================= --}}
<section data-view="brands" hidden>
    <div class="h2row"><h2>Brands</h2>@if($isAdmin)<a class="btn primary" href="{{ route('brands.create') }}">+ Add brand</a>@endif</div>
    @include('partials.brand-grid')
    <div class="h2row" style="margin-top:24px"><h2>Brand details</h2><span class="sub">Open a brand for every account, tool and routine in one place</span></div>
    <div class="brands">
        @foreach($brands as $b)
            @php($count = $tools->filter(fn ($t) => $t->all_brands || $t->brands->contains('id', $b->id))->count())
            <article class="brand" data-brand-card="{{ $b->slug }}" data-search="{{ strtolower($b->name.' '.$b->focus.' '.$b->channels.' '.$b->notes) }}">
                <div class="band" style="background:var(--c{{ $b->colorSlot() }})"></div>
                <div class="in">
                    <div>
                        <h3><a href="{{ route('brands.show', $b) }}" class="brand-link">{{ $b->name }}</a></h3>
                        @if($b->site)
                            <a class="site" href="{{ $b->site }}" target="_blank" rel="noopener noreferrer">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($b->site, '/')) }} ↗</a>
                        @else
                            <span class="site missing">No site added</span>
                        @endif
                    </div>
                    <dl class="kv">
                        <dt>Focus</dt><dd>{!! $b->focus ? e($b->focus) : '<span class="missing">Not described yet</span>' !!}</dd>
                        <dt>Channels</dt><dd>{!! $b->channels ? e($b->channels) : '<span class="missing">Not listed</span>' !!}</dd>
                        <dt>Social</dt>
                        <dd>
                            @forelse($b->socials ?? [] as $s)
                                @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer">{{ $s['label'] ?: parse_url($s['url'], PHP_URL_HOST) }}</a>@else{{ $s['label'] }}@endif{{ !$loop->last ? ' · ' : '' }}
                            @empty
                                <span class="missing">No handles added</span>
                            @endforelse
                        </dd>
                        @if($b->notes)<dt>Notes</dt><dd>{{ $b->notes }}</dd>@endif
                    </dl>
                    <div class="brand-actions">
                        <a class="btn primary" href="{{ route('brands.show', $b) }}">Open {{ $b->name }} hub →</a>
                        <button class="btn" type="button" data-showtools="{{ $b->slug }}">{{ $count }} tools</button>
                        <span class="spacer"></span>
                        @if($isAdmin)<a class="btn ghost" href="{{ route('brands.edit', $b) }}">Edit</a>@endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>

{{-- ================= ROUTINES ================= --}}
<section data-view="routines">
    <div class="h2row">
        <h2>Routines</h2>
        <span class="sub">Ticks reset each day, week, month or quarter (Eastern time).</span>
        @if($isAdmin)<a class="btn primary" href="{{ route('routines.create') }}">+ Add routine</a>@endif
    </div>
    <div class="routines">
        @foreach(config('omniven.cadences') as $key => $label)
            @php($rs = $routines->where('cadence', $key))
            <div class="rcol">
                <h3>{{ $label }}<small>{{ $rs->filter->isDone()->count() }}/{{ $rs->count() }} done</small></h3>
                @forelse($rs as $r)
                    @php($done = $r->isDone())
                    <div class="r {{ $done ? 'done' : '' }}" data-brands="{{ $r->brandFilterAttr() }}" data-search="{{ strtolower($r->title.' '.$r->description) }}">
                        <form method="POST" action="{{ route('routines.toggle', $r) }}">
                            @csrf
                            <button class="tick" type="submit" aria-pressed="{{ $done ? 'true' : 'false' }}" aria-label="{{ $done ? 'Mark not done' : 'Mark done' }}: {{ $r->title }}">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                            </button>
                        </form>
                        <div class="rbody">
                            <div class="rt">{{ $r->title }}</div>
                            @if($r->description)<div class="rd">{{ $r->description }}</div>@endif
                            <div class="rm">
                                @if($r->link)<a href="{{ $r->link }}" target="_blank" rel="noopener noreferrer">Open {{ preg_replace('/^www\./', '', parse_url($r->link, PHP_URL_HOST)) }} ↗</a>@endif
                                @if($done)
                                    <span class="ok">✓ {{ $r->latestCompletion->completed_at->format('M j, g:ia') }}{{ $r->latestCompletion->user ? ' · '.$r->latestCompletion->user->firstName() : '' }}</span>
                                @elseif($r->latestCompletion)
                                    <span>Last done {{ $r->latestCompletion->completed_at->format('M j') }}</span>
                                @endif
                                <span>{{ implode(', ', $r->brandLabels()) }}</span>
                            </div>
                        </div>
                        @if($isAdmin)<a class="mini" href="{{ route('routines.edit', $r) }}">Edit</a>@endif
                    </div>
                @empty
                    <div class="r"><span></span><span class="sub">No {{ strtolower($label) }} routines yet.</span></div>
                @endforelse
            </div>
        @endforeach
    </div>
</section>

@include('partials.tasks-tab')
@include('partials.postit-tab')

{{-- ================= SLACK ================= --}}
<section data-view="slack" hidden>
    <div class="h2row"><h2>Slack</h2><span class="sub">Recent messages from our channels. Updates every minute while this tab is open.</span></div>
    <div id="slack-feed" data-url="{{ route('slack.feed') }}" data-unread-url="{{ route('slack.unread') }}"><div class="empty">Loading Slack…</div></div>
</section>

{{-- ================= ACCESS ================= --}}
<section data-view="access" hidden>
    @if($isAdmin)@include('partials.access-requests')@include('partials.broken-links')@endif
    <div class="h2row"><h2>Access &amp; owners</h2><span class="sub">Which account each tool lives under and how to get in. Need a login? Use Request access.</span>@if($isAdmin)<a class="btn" href="{{ route('access.review') }}">Access review</a><a class="btn primary" href="{{ route('tools.create', ['return' => 'access']) }}">+ Add item</a>@endif</div>
    <div class="callout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <div>Never put passwords or 2FA codes in OmniVEN. List the login account and who grants access; credentials stay in the password manager.</div>
    </div>
    <div class="tablewrap">
        <table>
            <thead><tr><th>Tool</th><th>Status</th><th>Login account</th><th>Owner</th><th>How to get access</th><th>Brands</th><th><span class="sr">Request access</span></th>@if($isAdmin)<th class="actions-h"><span class="sr">Actions</span></th>@endif</tr></thead>
            <tbody>
                @foreach($tools->sortBy(fn ($t) => [array_search($t->category, config('omniven.categories')) ?: 99, $t->name]) as $t)
                    <tr data-brands="{{ $t->brandFilterAttr() }}" data-search="{{ strtolower($t->name.' '.$t->login.' '.$t->owner.' '.$t->access_note.' '.$t->category) }}">
                        <td>@if($t->url)<a href="{{ $t->url }}" target="_blank" rel="noopener noreferrer">{{ $t->name }}</a>@else{{ $t->name }}@endif<div class="sub">{{ $t->category }}</div></td>
                        <td><span class="pill {{ $t->status }}">{{ $t->statusLabel() }}</span></td>
                        <td>{!! $t->login ? e($t->login) : '<span class="missing">Add</span>' !!}</td>
                        <td>{!! $t->owner ? e($t->owner) : '<span class="missing">Add</span>' !!}</td>
                        <td>{!! $t->access_note ? e($t->access_note) : '<span class="missing">—</span>' !!}</td>
                        <td>{{ implode(', ', $t->brandLabels()) }}</td>
                        <td>@include('partials.request-access', ['item' => $t])</td>
                        @if($isAdmin)
                            <td class="row-actions">
                                <a class="mini" href="{{ route('tools.edit', [$t, 'return' => 'access']) }}">Edit</a>
                                <form method="POST" action="{{ route('tools.destroy', [$t, 'return' => 'access']) }}" data-confirm>@csrf @method('DELETE')
                                    <button class="mini danger-link" type="submit" data-label="Delete">Delete</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php($allAccounts = \App\Models\Account::with('brand')->get()->sortBy(fn ($a) => [$a->brand?->sort ?? 0, $a->platform]))
    <div class="h2row" style="margin-top:28px"><h2>Brand accounts</h2><span class="sub">Each brand's own social, ad, analytics and email accounts</span>@if($isAdmin)<a class="btn" href="{{ route('accounts.create', ['return' => 'access']) }}">+ Add account</a>@endif</div>
    @if($allAccounts->isEmpty())
        <div class="empty">No brand accounts yet. Add them from a brand's hub or the grid on the Brands tab.</div>
    @else
        <div class="tablewrap">
            <table>
                <thead><tr><th>Account</th><th>ID</th><th>Login account</th><th>Owner</th><th>Verified</th><th><span class="sr">Request access</span></th>@if($isAdmin)<th><span class="sr">Actions</span></th>@endif</tr></thead>
                <tbody>
                    @foreach($allAccounts as $a)
                        <tr data-brands="{{ $a->brand?->slug ?? 'all' }}" data-search="{{ strtolower($a->title().' '.$a->login.' '.$a->owner.' '.$a->account_ref) }}">
                            <td class="with-star">@include('partials.star', ['item' => $a])@if($a->openUrl())<a href="{{ $a->openUrl() }}" target="_blank" rel="noopener noreferrer" data-lp="{{ $a->launchKey() }}">{{ $a->title() }}</a>@else{{ $a->title() }}@endif</td>
                            <td class="ts">{{ $a->account_ref ?: '—' }}</td>
                            <td>{!! $a->login ? e($a->login) : '<span class="missing">Add</span>' !!}</td>
                            <td>{!! $a->owner ? e($a->owner) : '<span class="missing">Add</span>' !!}</td>
                            <td class="{{ $a->needsVerify() ? 'warn-t' : 'ok-t' }}">{{ $a->verified_on?->format('M j, Y') ?? 'Never' }}</td>
                            <td>@include('partials.request-access', ['item' => $a])</td>
                            @if($isAdmin)<td class="row-actions"><a class="mini" href="{{ route('accounts.edit', [$a, 'return' => 'access']) }}">Edit</a></td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

</main>

<span id="session-expiry" data-ends="{{ $sessionEndsAt }}" data-me="{{ auth()->id() }}" @if($goHome) data-home="1" @endif hidden></span>
@include('partials.hotlist-popup')
@endsection
