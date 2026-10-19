{{-- Ask OmniVEN AI's two tools. --}}
<nav class="seg as-tabs" aria-label="Ask OmniVEN AI tools">
    <a class="seg-b" href="{{ route('assistant') }}" aria-pressed="{{ request()->routeIs('assistant*') ? 'true' : 'false' }}">Ask OmniVEN AI</a>
    <a class="seg-b" href="{{ route('taskbuddy') }}" aria-pressed="{{ request()->routeIs('taskbuddy*') ? 'true' : 'false' }}">TaskBuddy AI</a>
</nav>
