<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · ' : '' }}OmniVEN</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Source+Sans+3:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/omniven.css') }}?v={{ filemtime(public_path('css/omniven.css')) }}">
    <script src="{{ asset('js/omniven.js') }}?v={{ filemtime(public_path('js/omniven.js')) }}" defer></script>
</head>
<body>
@auth
<header class="appbar">
    <div class="wrap appbar-in">
        <a href="{{ route('dashboard', ['home' => 1]) }}" class="logo" aria-label="OmniVEN home: Routines for all brands">
            <picture>
                <source srcset="{{ asset('images/omniven-logo-dark.png') }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset('images/omniven-logo.png') }}" alt="OmniVEN" width="174" height="57">
            </picture>
        </a>
        <button type="button" class="launch-btn" id="launch-open" aria-haspopup="dialog" aria-keyshortcuts="Meta+K Control+K">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span>Jump to…</span><kbd class="kbd-mac">⌘K</kbd><kbd class="kbd-pc">Ctrl K</kbd>
        </button>
        <nav class="who">
            @can('view-activity')
                <a href="{{ route('activity.index') }}" class="btn ghost">Activity</a>
            @endcan
            @can('admin')
                <a href="{{ route('people.index') }}" class="btn ghost">People</a>
            @endcan
            <span class="me">
                @if(auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatar }}" alt="" width="28" height="28" referrerpolicy="no-referrer">
                @endif
                <span>{{ auth()->user()->firstName() }}</span>
                @if(auth()->user()->is_admin)<span class="role">Admin</span>@endif
            </span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost" type="submit">Sign out</button></form>
        </nav>
    </div>
</header>
@endauth

@if(session('status') || session('error'))
    <div class="flash {{ session('error') ? 'flash-error' : '' }}" role="status">
        <div class="wrap">{{ session('error') ?? session('status') }}</div>
    </div>
@endif

@yield('content')

@auth
<dialog id="launcher" class="launcher" aria-label="Jump to anything">
    <div class="ln-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="ln-q" type="text" autocomplete="off" spellcheck="false" placeholder="Brand, account or tool, e.g. ocusafe insta" aria-label="Search brands, accounts and tools" aria-controls="ln-list">
        <kbd>Esc</kbd>
    </div>
    <ul id="ln-list" class="ln-list" role="listbox" data-src="{{ route('launcher') }}" data-fav="{{ route('launchpad.favorite') }}" data-open="{{ route('launchpad.open') }}" data-icons="{{ \App\Support\BrandIcon::spriteUrl() }}"></ul>
    <p class="ln-foot sub">↑ ↓ to move · Enter to open · ⇧Enter to star · Accounts and tools open in a new tab</p>
</dialog>
@endauth
</body>
</html>
