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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/omniven.css') }}?v={{ filemtime(public_path('css/omniven.css')) }}">
    {{-- Applies a saved light/dark choice before the page paints (a file, so the CSP allows it). --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    <script src="{{ asset('js/omniven.js') }}?v={{ filemtime(public_path('js/omniven.js')) }}" defer></script>
</head>
<body>
@auth
@php($menu = \App\Support\Menu::for(auth()->user()))
<div class="shell" id="shell">
<aside class="sidebar" id="sidebar" aria-label="Main">
    <div class="sb-head">
        <a href="{{ route('dashboard', ['home' => 1]) }}" class="logo" aria-label="OmniVEN home: Routines for all brands">
            <img class="logo-full logo-light" src="{{ asset('images/omniven-logo.png') }}" alt="OmniVEN" width="150" height="49">
            <img class="logo-full logo-dark" src="{{ asset('images/omniven-logo-dark.png') }}" alt="OmniVEN" width="150" height="49">
            <img class="logo-mark" src="{{ asset('apple-touch-icon.png') }}" alt="" width="32" height="32">
        </a>
        <button type="button" class="sb-close" data-sidebar-close aria-label="Close menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    </div>
    <nav class="sb-nav" id="mainnav" aria-label="Main menu">
        <h2 class="sb-label"><span>Menu</span><i aria-hidden="true">···</i></h2>
        <ul class="sb-list">
            @foreach($menu['groups'] as $key => $g)
                <li>
                    <details class="sb-group" data-sb-group="{{ $key }}" @if($g['active']) open @endif>
                        <summary @class(['sb-item', 'is-here' => $g['active']]) title="{{ $g['label'] }}">
                            @include('partials.sb-icon', ['name' => $key])
                            <span class="sb-text">{{ $g['label'] }}</span>
                            <svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <ul class="sb-sub">
                            @foreach($g['items'] as $i)
                                <li><a href="{{ $i['u'] }}" title="{{ $i['s'] }}" @if($i['hash']) data-sb-hash="{{ $i['hash'] }}" @endif @if($i['route'] !== 'dashboard' && request()->routeIs($i['route'])) aria-current="page" @endif>{{ $i['t'] }}</a></li>
                            @endforeach
                        </ul>
                    </details>
                </li>
            @endforeach
        </ul>
        @if($menu['account'])
            <h2 class="sb-label"><span>Others</span><i aria-hidden="true">···</i></h2>
            <ul class="sb-list">
                @foreach($menu['account'] as $i)
                    <li><a class="sb-item" href="{{ $i['u'] }}" title="{{ $i['t'] }}" @if(request()->routeIs($i['route'])) aria-current="page" @endif>@include('partials.sb-icon', ['name' => $i['route']])<span class="sb-text">{{ $i['t'] }}</span></a></li>
                @endforeach
            </ul>
        @endif
    </nav>
    @if($menu['ai'])
        <div class="sb-promo">
            <b>Ask OmniVEN AI</b>
            <p>Analysis and next steps for any brand, number or task, or turn a document into tasks.</p>
            <a class="btn primary" href="{{ route('assistant') }}">Ask a question</a>
            <a class="sb-promo-2" href="{{ route('taskbuddy') }}">TaskBuddy AI →</a>
        </div>
    @endif
</aside>
<div class="nav-scrim" id="nav-scrim" hidden></div>
<div class="shell-main">
<header class="topbar" id="appbar">
    <div class="topbar-in">
        <button type="button" class="tb-toggle" id="nav-toggle" aria-expanded="false" aria-controls="sidebar" aria-label="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
        </button>
        <a href="{{ route('dashboard', ['home' => 1]) }}" class="logo topbar-logo" aria-label="OmniVEN home">
            <img class="logo-light" src="{{ asset('images/omniven-logo.png') }}" alt="OmniVEN" width="120" height="39">
            <img class="logo-dark" src="{{ asset('images/omniven-logo-dark.png') }}" alt="OmniVEN" width="120" height="39">
        </a>
        <button type="button" class="launch-btn" id="launch-open" aria-haspopup="dialog" aria-keyshortcuts="Meta+K Control+K" aria-label="Jump to anything">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span>Jump to a brand, account or tool…</span><kbd class="kbd-mac">⌘K</kbd><kbd class="kbd-pc">Ctrl K</kbd>
        </button>
        <div class="topbar-r">
            @if($menu['ai'])
                <a href="{{ route('assistant') }}" class="ai-btn ai-top" @if(request()->routeIs('assistant*')) aria-current="page" @endif>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4zM19 14l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9zM5 15l.7 1.8 1.8.7-1.8.7L5 20l-.7-1.8-1.8-.7 1.8-.7z"/></svg>
                    <span>Ask OmniVEN AI</span>
                </a>
            @endif
            <button type="button" class="tb-icon" data-theme-toggle aria-label="Switch between light and dark" title="Light or dark">
                <svg class="i-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="i-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            <details class="me-menu" data-menu>
                <summary aria-label="Your account and admin pages">
                    @if(auth()->user()->avatar)
                        <img src="{{ auth()->user()->avatar }}" alt="" width="36" height="36" referrerpolicy="no-referrer">
                    @else
                        <span class="mm-ini" aria-hidden="true">{{ mb_substr(auth()->user()->firstName(), 0, 1) }}</span>
                    @endif
                    <span class="mm-name">{{ auth()->user()->firstName() }}</span>
                    <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="mm-panel">
                    <p class="mm-who"><b>{{ auth()->user()->name }}</b><span>{{ auth()->user()->email }}</span>@if(auth()->user()->is_admin)<span class="role">Admin</span>@endif</p>
                    <ul>
                        <li><a href="{{ route('my-day') }}"><b>My day</b><span>Your tasks and routines for today</span></a></li>
                        @foreach($menu['account'] as $i)
                            <li><a href="{{ $i['u'] }}" @if(request()->routeIs($i['route'])) aria-current="page" @endif><b>{{ $i['t'] }}</b><span>{{ $i['s'] }}</span></a></li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost" type="submit">Sign out</button></form>
                </div>
            </details>
        </div>
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
    @include('partials.stuck-postits')
</div>{{-- .shell-main --}}
</div>{{-- .shell --}}
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
