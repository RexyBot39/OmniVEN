@extends('layouts.app', ['title' => 'Reddit'])
@use('App\Models\RedditThread')

@section('content')
<main class="wrap sales cgaps">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Reddit</span>@if($status['last'])<span>Updated {{ $status['last']->diffForHumans() }} · hourly</span>@endif</div>
            <h1 class="form-h">Reddit opportunities @include('partials.star', ['key' => 'page:25', 'label' => 'Reddit'])</h1>
            <p class="sub">Threads from the past week worth a reply, best first. Make one a task (due today) or skip it.</p>
        </div>
    </div>

    @if(! $configured)
        <div class="empty sales-setup">
            <p><b>Connect Reddit to find threads worth a reply.</b></p>
            @can('admin')
                <ol>
                    <li>Signed in to Reddit, open <a href="https://www.reddit.com/prefs/apps" target="_blank" rel="noopener noreferrer">reddit.com/prefs/apps</a> → <b>create another app</b> → type <b>script</b>, any name, redirect <code>http://localhost</code>.</li>
                    <li>In Railway, set <code>REDDIT_CLIENT_ID</code> (the code under the app’s name) and <code>REDDIT_CLIENT_SECRET</code>. OmniVEN only reads; it never posts.</li>
                </ol>
            @else
                <p class="sub">Ask an admin to connect it.</p>
            @endcan
        </div>
    @else
        @if($status['error'])
            <div class="callout warn-callout" role="status"><div><b>The last update from Reddit failed.</b>@can('admin') {{ $status['error'] }}@endcan</div></div>
        @endif

        @if($counts->sum())
            <nav class="gap-kinds" aria-label="Kind of thread">
                <a href="{{ route('reddit') }}" @if(! $kind) aria-current="true" @endif>All <span>{{ $counts->sum() }}</span></a>
                @foreach(RedditThread::KINDS as $k => $label)
                    @if($counts[$k] ?? 0)<a href="{{ route('reddit', ['kind' => $k]) }}" @if($kind === $k) aria-current="true" @endif>{{ $label }} <span>{{ $counts[$k] }}</span></a>@endif
                @endforeach
            </nav>
        @endif

        @if($threads->isEmpty())
            <div class="empty">{{ $status['last'] ? 'Nothing worth a reply right now. New threads are checked every hour.' : 'Looking on Reddit… refresh in a minute.' }}</div>
        @else
            <ol class="gap-list">
                @foreach($threads as $t)
                    <li class="card cgap rd-{{ $t->kind }}" style="--bc:{{ $t->brand ? 'var(--c'.$t->brand->colorSlot().')' : 'var(--line)' }}">
                        <div class="gap-main">
                            <div class="gap-top"><span class="gap-kind k-{{ ['complaint' => 'complaint', 'mention' => 'striking', 'recommend' => 'ai', 'question' => 'question'][$t->kind] }}">{{ RedditThread::KINDS[$t->kind] }}</span>@if($t->brand)<span class="bpill" style="--bc:var(--c{{ $t->brand->colorSlot() }})">{{ $t->brand->name }}</span>@endif<span class="sub">r/{{ $t->subreddit }} · {{ $t->posted_at->diffForHumans() }} · {{ $t->comments }} {{ Str::plural('comment', $t->comments) }}</span></div>
                            <h2 class="gap-q"><a href="{{ $t->url }}" target="_blank" rel="noopener noreferrer">{{ $t->title }}</a></h2>
                            @if($t->matched)<p class="gap-why sub">Matched: {{ implode(', ', $t->matched) }}</p>@endif
                        </div>
                        <div class="gap-act">
                            <form method="POST" action="{{ route('reddit.task', $t) }}" class="gap-task">
                                @csrf
                                <label><span class="sr">Who replies</span><select name="assignee_id">@foreach($people as $p)<option value="{{ $p->id }}" @selected($p->id === auth()->id())>{{ $p->id === auth()->id() ? 'Me' : $p->name }}</option>@endforeach</select></label>
                                <button class="btn primary" type="submit">Make a task</button>
                            </form>
                            <form method="POST" action="{{ route('reddit.skip', $t) }}">@csrf<button class="mini" type="submit">Skip</button></form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    @endif

    @if($recent->isNotEmpty())
        <section>
            <div class="h2row"><h2>Recently handled</h2></div>
            <ul class="review-list">
                @foreach($recent as $r)
                    <li><a href="{{ $r->url }}" target="_blank" rel="noopener noreferrer">{{ Str::limit($r->title, 90) }}</a>
                        <span class="sub">· r/{{ $r->subreddit }} ·
                            @if($r->status === 'tasked')
                                @if($r->task){{ $r->task->isDone() ? 'Replied' : 'Task' }} · {{ $r->task->assignee?->firstName() }}@if($r->task->outcome_url) · <a href="{{ $r->task->outcome_url }}" target="_blank" rel="noopener noreferrer">reply</a>@endif @else Task deleted @endif
                            @else
                                Skipped by {{ $r->user?->firstName() ?? 'someone' }}
                            @endif
                        </span>
                        @if($r->status === 'skipped')<form method="POST" action="{{ route('reddit.restore', $r) }}" class="inline-f">@csrf<button class="mini" type="submit">Restore</button></form>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
@endsection
