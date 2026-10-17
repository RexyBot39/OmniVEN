@extends('layouts.app', ['title' => $run->name])

@section('content')
<main class="wrap aians">
    <a class="back" href="{{ route('templates.index') }}">← Launch templates</a>
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>{{ $run->template?->name ?? 'Template' }}</span>@if($run->brand)<span>{{ $run->brand->name }}</span>@endif<span>Starts {{ $run->starts_on->format('D, M j, Y') }}</span>@if($run->user)<span>Set up by {{ $run->user->firstName() }}</span>@endif</div>
            <h1 class="form-h">{{ $run->name }}</h1>
        </div>
    </div>
    @php $done = $tasks->whereNotNull('completed_at')->count(); @endphp
    <p><span class="share wide"><i style="width:{{ $tasks->count() ? round($done / $tasks->count() * 100) : 0 }}%"></i></span><span class="share-t">{{ $done }} of {{ $tasks->count() }} done</span></p>
    <ul class="rv-list">
        @foreach($tasks as $t)
            <li class="rv-item {{ $t->completed_at ? '' : ($t->isOverdue() ? 'low' : '') }}">
                <span class="rv-stars">{{ $t->completed_at ? '✓' : '○' }}</span>
                <div class="rv-body">
                    <div class="rv-meta"><b>{{ $t->assignee?->firstName() }}</b> · {{ $t->completed_at ? 'Done '.$t->completed_at->timezone(config('app.timezone'))->format('M j') : ($t->dueLabel() ?? '') }}</div>
                    <p>@if($t->completed_at)<s>{{ $t->title }}</s>@else{{ $t->title }}@endif</p>
                    @if($t->outcome || $t->outcome_url)<p class="outcome-v">→ {{ $t->outcome }}@if($t->outcome_url) <a href="{{ $t->outcome_url }}" target="_blank" rel="noopener noreferrer">{{ $t->outcome ? 'Open' : Str::limit(preg_replace('#^https?://(www\.)?#', '', $t->outcome_url), 60) }}</a>@endif</p>@endif
                </div>
            </li>
        @endforeach
    </ul>
    <p class="sub">Tick these off on the Tasks board like any other task.</p>
</main>
@endsection
