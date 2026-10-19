@extends('layouts.app', ['title' => $run ? $run->title.' · TaskBuddy AI' : 'TaskBuddy AI'])

@section('content')
<main class="wrap sales assistant taskbuddy">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Ask OmniVEN AI</span><span>Documents, files or notes into tasks</span></div>
            <h1 class="form-h">TaskBuddy AI @include('partials.star', ['key' => 'page:31', 'label' => 'TaskBuddy AI'])</h1>
            <p class="sub">Give it a document, a file or pasted notes. It drafts an organized task list with every field filled in, asks if anything’s unclear, and you add the tasks you keep.</p>
        </div>
        <div class="as-head-r">
            @include('assistant.tabs')
            @if($configured && $run)<a class="btn" href="{{ route('taskbuddy') }}">+ New task list</a>@endif
        </div>
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>TaskBuddy AI isn’t switched on yet.</b> It uses the same OpenAI key as Ask OmniVEN AI.</p>
            @if($isAdmin)<p class="sub">In Railway, set <code>OPENAI_API_KEY</code> (see Help → Settings).</p>@else<p class="sub">Ask an admin to switch it on.</p>@endif
        </div>
    @else
        <div class="as-layout">
            <aside class="as-side" aria-label="Your task lists">
                <h2 class="as-side-h">Your task lists</h2>
                @forelse($runs as $r)
                    <a href="{{ route('taskbuddy.show', $r) }}" @if($run?->is($r)) aria-current="true" @endif>{{ \Illuminate\Support\Str::limit($r->title, 60) }}<span class="sub">{{ $r->created_at->timezone(config('app.timezone'))->format('M j') }} · {{ ['pending' => 'Working…', 'questions' => 'Has questions', 'ready' => 'Ready to review', 'added' => count($r->task_ids ?? []).' added', 'error' => 'Didn’t work'][$r->status] ?? $r->status }}</span></a>
                @empty
                    <p class="sub">None yet. Only you can see yours.</p>
                @endforelse
            </aside>

            <section class="as-chat" aria-label="TaskBuddy AI">
                @include('partials.form-errors')
                @if(! $run)
                    @include('taskbuddy.start')
                @elseif($run->status === 'pending')
                    <div class="card tb-wait" data-tb-poll="{{ route('taskbuddy.status', $run) }}">
                        <p class="as-thinking"><span class="as-dots" aria-hidden="true"><i></i><i></i><i></i></span> {{ $run->rounds ? 'Updating the list with your answers…' : 'Reading '.($run->source_name ?: 'your text').' and organizing the tasks…' }}</p>
                        <p class="sub">This usually takes under a minute; longer documents take longer. The page updates by itself.</p>
                    </div>
                @elseif($run->status === 'error')
                    <div class="card tb-err">
                        <p><b>TaskBuddy couldn’t make this list.</b> {{ $run->error }}</p>
                        @if($run->source_text || $run->source_file)
                            <form method="POST" action="{{ route('taskbuddy.answer', $run) }}">@csrf<input type="hidden" name="retry" value="1"><button class="btn primary" type="submit">Try again</button></form>
                        @endif
                    </div>
                @elseif($run->status === 'added')
                    <div class="card tb-done">
                        <p><b>✓ {{ count($run->task_ids ?? []) }} {{ \Illuminate\Support\Str::plural('task', count($run->task_ids ?? [])) }} added</b> on {{ $run->updated_at->timezone(config('app.timezone'))->format('M j, g:i A') }}.</p>
                        <p><a class="btn" href="{{ route('dashboard') }}#tasks">See them on the task board →</a> <a class="btn" href="{{ route('taskbuddy') }}">Make another list</a></p>
                        <p class="sub">The original was deleted when the tasks were added.</p>
                    </div>
                @else
                    @if($run->summary)<div class="card tb-summary"><p>{{ $run->summary }}</p></div>@endif
                    @if($run->status === 'questions')
                        @include('taskbuddy.questions')
                    @else
                        @include('taskbuddy.review')
                    @endif
                @endif
                @if($run && $run->status !== 'pending')
                    <form method="POST" action="{{ route('taskbuddy.destroy', $run) }}" class="as-del" data-confirm>@csrf @method('DELETE')<button class="mini linkish" type="submit" data-label="Delete this task list">Delete this task list</button></form>
                @endif
            </section>
        </div>
    @endif
</main>
@endsection
