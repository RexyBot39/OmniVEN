@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
<main class="login">
    <div class="login-card">
        <picture>
            <source srcset="{{ asset('images/omniven-logo-dark.png') }}" media="(prefers-color-scheme: dark)">
            <img src="{{ asset('images/omniven-logo.png') }}" alt="OmniVEN" class="login-logo" width="300" height="99">
        </picture>
        <p class="login-lede">The marketing control center for 39DollarGlasses, Ocusafe, Neurolux and every sister brand.</p>

        @if(session('error'))
            <p class="login-msg error" role="alert">{{ session('error') }}</p>
        @elseif(session('status'))
            <p class="login-msg" role="status">{{ session('status') }}</p>
        @endif

        <a href="{{ route('google.redirect') }}" class="google-btn">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
            Sign in with Google
        </a>
        <p class="login-foot">Use your <strong>{{ '@'.config('omniven.allowed_domain') }}</strong> work account.</p>
    </div>
</main>
@endsection
