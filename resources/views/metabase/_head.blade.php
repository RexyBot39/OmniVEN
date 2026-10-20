{{-- Metabase header: where you are, Browse / New query, a link to the same page in Metabase, and setup steps until it's connected. --}}
<div class="act-head">
    <div>
        <div class="eyebrow"><span>Revenue</span><span>From Metabase</span><span>{{ parse_url(config('metabase.url'), PHP_URL_HOST) }}</span></div>
        <h1 class="form-h">{{ $title }} @if(! empty($star))@include('partials.star', ['key' => 'page:40', 'label' => 'Metabase'])@endif</h1>
        @if(! empty($lede))<p class="sub">{{ $lede }}</p>@endif
    </div>
    <div class="mb-head-acts">
        @if(! empty($open))<a class="btn ghost" href="{{ $open }}" target="_blank" rel="noopener noreferrer">Open in Metabase ↗</a>@endif
        <nav class="seg" aria-label="Metabase">
            <a class="seg-b" href="{{ route('metabase') }}" aria-pressed="{{ request()->routeIs('metabase') || request()->routeIs('metabase.question') || request()->routeIs('metabase.dashboard') ? 'true' : 'false' }}">Browse</a>
            <a class="seg-b" href="{{ route('metabase.query') }}" aria-pressed="{{ request()->routeIs('metabase.query') || request()->routeIs('metabase.run') ? 'true' : 'false' }}">New query</a>
        </nav>
    </div>
</div>

@if(! \App\Support\Metabase\Metabase::configured())
    <div class="empty sales-setup">
        <p><b>Connect Metabase to see its questions and dashboards here.</b> Until then only admins can see this page.</p>
        <ol>
            <li>In Metabase, open <b>Admin settings → Authentication → API keys</b> and create a key. Put it in a group that can see the collections and databases OmniVEN should use (view data and, for new queries, native query access).</li>
            <li>In Railway, set <code>METABASE_API_KEY</code>. If Metabase moves, also set <code>METABASE_URL</code> (now {{ config('metabase.url') }}).</li>
            <li>Make sure Metabase connects to the database with a read-only login. OmniVEN only runs queries that read data, but the database should enforce it too.</li>
        </ol>
    </div>
@elseif(! empty($error))
    <div class="callout warn-callout" role="status"><div>{{ $error }}</div></div>
@endif
