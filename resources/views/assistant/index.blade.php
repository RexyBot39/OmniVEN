@extends('layouts.app', ['title' => $conv ? $conv->title : 'Ask OmniVEN AI'])

@section('content')
<main class="wrap sales assistant">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Assistant</span><span>Reads OmniVEN’s data, never changes it</span></div>
            <h1 class="form-h">Ask OmniVEN AI @include('partials.star', ['key' => 'page:30', 'label' => 'Ask OmniVEN AI'])</h1>
            <p class="sub">Ask about any brand, section, number or task. It looks things up across OmniVEN and suggests what to do next.</p>
        </div>
        @if($configured)<a class="btn" href="{{ route('assistant') }}">+ New conversation</a>@endif
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Ask OmniVEN AI isn’t switched on yet.</b></p>
            @if($isAdmin)
                <ol>
                    <li>At platform.openai.com → <b>API keys</b>, create a secret key in the project you want billed.</li>
                    <li>In Railway, set <code>OPENAI_API_KEY</code>. It uses <code>gpt-6.1-sol</code> at high reasoning effort unless you set <code>OPENAI_MODEL</code> or <code>OPENAI_REASONING_EFFORT</code>.</li>
                    <li>Questions and the OmniVEN data needed to answer them are sent to OpenAI. Nothing is stored there (store is off), and sticky notes, Slack messages, logins and keys are never sent.</li>
                </ol>
            @else
                <p class="sub">Ask an admin to switch it on.</p>
            @endif
        </div>
    @else
        <div class="as-layout">
            <aside class="as-side" aria-label="Your conversations">
                <h2 class="as-side-h">Your conversations</h2>
                @forelse($conversations as $c)
                    <a href="{{ route('assistant.show', $c) }}" @if($conv?->is($c)) aria-current="true" @endif>{{ \Illuminate\Support\Str::limit($c->title, 70) }}<span class="sub">{{ $c->updated_at->timezone(config('app.timezone'))->format('M j') }}</span></a>
                @empty
                    <p class="sub">None yet. Only you can see yours.</p>
                @endforelse
                @if($isAdmin && $usage)
                    <p class="sub as-cost">This month, everyone: {{ $usage['answers'] }} {{ \Illuminate\Support\Str::plural('answer', $usage['answers']) }}, about ${{ number_format($usage['cost'], 2) }}. Model {{ $model }}, effort {{ $effort }}.</p>
                @endif
            </aside>

            <section class="as-chat" aria-label="Conversation">
                @if($conv)
                    <div class="as-thread">
                        @foreach($conv->messages as $m)@include('assistant.message', ['m' => $m, 'isAdmin' => $isAdmin])@endforeach
                    </div>
                @else
                    <div class="as-start">
                        <h2>Try one of these</h2>
                        <div class="as-ideas">
                            @foreach([
                                ['', 'What should the team focus on this week to grow sales, and why?'],
                                ['sales', 'How is revenue doing this month against last year and our goal, by brand and channel? What explains the biggest changes?'],
                                ['brand:ocusafe', 'Give me a full health check of Ocusafe: sales, search, reviews, accounts and open work. What are the top three fixes?'],
                                ['tasks', 'Which work is overdue or slipping, who has too much on their plate, and what should be reassigned?'],
                                ['search', 'Where are we losing search clicks, and which content gaps would win back the most traffic?'],
                                ['reputation', 'How are our reviews, Reddit mentions and AI-assistant answers trending, and what should we respond to first?'],
                                ['campaigns', 'Review our running and upcoming campaigns. What is behind schedule and what is missing?'],
                                ['site', 'Which structured data, feed and account problems are costing us the most, and in what order should we fix them?'],
                            ] as [$f, $q])
                                <a class="as-idea" href="{{ route('assistant', array_filter(['q' => $q, 'focus' => $f])) }}">{{ $q }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('assistant.ask') }}" class="card as-ask" id="as-form">
                    @csrf
                    @if($conv)<input type="hidden" name="conversation_id" value="{{ $conv->id }}">@endif
                    @include('partials.form-errors')
                    <label for="as-q" class="sr">Your question</label>
                    <textarea id="as-q" name="question" rows="3" maxlength="4000" required placeholder="{{ $conv ? 'Ask a follow-up…' : 'e.g. Why did organic search revenue drop for 39DollarGlasses last month?' }}">{{ old('question', $prefill) }}</textarea>
                    <div class="as-ask-row">
                        <label>Focus
                            <select name="focus" id="as-focus">
                                @foreach($focuses as $k => $label)<option value="{{ $k }}" @selected(old('focus', $focus) === $k)>{{ $label }}</option>@endforeach
                                <optgroup label="One brand">@foreach($brands as $b)<option value="brand:{{ $b->slug }}" @selected(old('focus', $focus) === 'brand:'.$b->slug)>{{ $b->name }}</option>@endforeach</optgroup>
                            </select>
                        </label>
                        <span class="sub">Press <kbd>⌘</kbd>/<kbd>Ctrl</kbd> + <kbd>Enter</kbd> to send.</span>
                        <button class="btn primary" type="submit">Ask</button>
                    </div>
                </form>
                @if($conv)
                    <form method="POST" action="{{ route('assistant.destroy', $conv) }}" class="as-del" data-confirm>@csrf @method('DELETE')<button class="mini linkish" type="submit" data-label="Delete this conversation">Delete this conversation</button></form>
                @endif
            </section>
        </div>
    @endif
</main>
@endsection
