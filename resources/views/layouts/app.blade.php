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
@php($menu = \App\Support\Menu::for(auth()->user()))
<header class="appbar" id="appbar">
    <div class="wrap appbar-in">
        <a href="{{ route('dashboard', ['home' => 1]) }}" class="logo" aria-label="OmniVEN home: Routines for all brands">
            <picture>
                <source srcset="{{ asset('images/omniven-logo-dark.png') }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset('images/omniven-logo.png') }}" alt="OmniVEN" width="174" height="57">
            </picture>
        </a>
        <button type="button" class="launch-btn" id="launch-open" aria-haspopup="dialog" aria-keyshortcuts="Meta+K Control+K" aria-label="Jump to anything">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span>Jump to…</span><kbd class="kbd-mac">⌘K</kbd><kbd class="kbd-pc">Ctrl K</kbd>
        </button>
        @if($menu['ai'])
            <a href="{{ route('assistant') }}" class="ai-btn ai-top" @if(request()->routeIs('assistant*')) aria-current="page" @endif>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4zM19 14l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9zM5 15l.7 1.8 1.8.7-1.8.7L5 20l-.7-1.8-1.8-.7 1.8-.7z"/></svg>
                <span>Ask OmniVEN AI</span>
            </a>
        @endif
        <details class="me-menu" data-menu>
            <summary aria-label="Your account and admin pages">
                @if(auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatar }}" alt="" width="28" height="28" referrerpolicy="no-referrer">
                @else
                    <span class="mm-ini" aria-hidden="true">{{ mb_substr(auth()->user()->firstName(), 0, 1) }}</span>
                @endif
                <span class="mm-name">{{ auth()->user()->firstName() }}</span>
                @if(auth()->user()->is_admin)<span class="role">Admin</span>@endif
                <svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </summary>
            <div class="mm-panel">
                <p class="mm-who"><b>{{ auth()->user()->name }}</b><span>{{ auth()->user()->email }}</span></p>
                <ul>
                    @foreach($menu['account'] as $i)
                        <li><a href="{{ $i['u'] }}" @if(request()->routeIs($i['route'])) aria-current="page" @endif><b>{{ $i['t'] }}</b><span>{{ $i['s'] }}</span></a></li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost" type="submit">Sign out</button></form>
            </div>
        </details>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="mainnav">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path class="nt-a" d="M4 7h16"/><path class="nt-b" d="M4 12h16"/><path class="nt-c" d="M4 17h16"/></svg>
            <span>Menu</span>
        </button>
    </div>
    <nav class="mainnav" id="mainnav" aria-label="Main">
        <div class="wrap mainnav-in">
            @if($menu['ai'])
                <a href="{{ route('assistant') }}" class="ai-btn ai-drawer">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4z"/></svg>
                    <span>Ask OmniVEN AI</span>
                </a>
            @endif
            <ul class="mg-bar">
                @foreach($menu['groups'] as $key => $g)
                    <li>
                        <details class="mg" data-menu>
                            <summary @class(['mg-sum', 'is-here' => $g['active']])>{{ $g['label'] }}<svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                            <div class="mg-panel">
                                <div class="wrap mg-in">
                                    <div class="mg-head">
                                        <h2>{{ $g['label'] }}</h2>
                                        <p>{{ $g['blurb'] }}</p>
                                        @if($menu['ai'])
                                            <a class="mg-ask" href="{{ route('assistant', ['focus' => $g['focus']]) }}">Ask OmniVEN AI about {{ mb_strtolower($g['label']) }} →</a>
                                        @endif
                                    </div>
                                    <ul class="mg-links">
                                        @foreach($g['items'] as $i)
                                            <li><a href="{{ $i['u'] }}" @if($i['route'] !== 'dashboard' && request()->routeIs($i['route'])) aria-current="page" @endif><b>{{ $i['t'] }}</b><span>{{ $i['s'] }}</span></a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </details>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
</header>
<div class="nav-scrim" id="nav-scrim" hidden></div>
@endauth

@if(session('status') || session('error'))
    <div class="flash {{ session('error') ? 'flash-error' : '' }}" role="status">
        <div class="wrap">{{ session('error') ?? session('status') }}</div>
    </div>
@endif

@yield('content')

@auth
    @include('partials.stuck-postits')
@endauth

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
