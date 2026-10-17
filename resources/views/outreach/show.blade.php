@extends('layouts.app', ['title' => $publisher->name])
@use('App\Models\OutreachEvent')
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap sales outreach">
    <a class="back" href="{{ route('outreach.index') }}">← Outreach</a>
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>{{ $types[$publisher->type] ?? $publisher->type }}</span>@if($publisher->brand)<span>For {{ $publisher->brand->name }}</span>@endif<span>Owner: {{ $publisher->owner?->name ?? 'nobody yet' }}</span></div>
            <h1 class="form-h">{{ $publisher->name }}</h1>
            <p class="sub">
                @if($publisher->site)<a href="{{ $publisher->site }}" target="_blank" rel="noopener noreferrer">{{ $publisher->host() }} ↗</a>@endif
                @if($publisher->contact) · {{ $publisher->contact }}@endif
            </p>
        </div>
        <a class="btn" href="{{ route('outreach.edit', $publisher) }}">Edit</a>
    </div>

    <section class="tiles sales-tiles" aria-label="Where it stands">
        <div class="tile"><span class="tl">Stage</span><span class="tv or-tv st-{{ $publisher->stage }}">{{ $publisher->stageLabel() }}</span></div>
        <div class="tile"><span class="tl">Follow up</span><span class="tv or-tv {{ $publisher->followUpDue() ? 'late' : '' }}">{{ $publisher->isOpen() && $publisher->follow_up_on ? $publisher->follow_up_on->format('D M j') : '—' }}</span>@if($publisher->followUpDue())<span class="sub">Due now</span>@endif</div>
        <div class="tile"><span class="tl">Last contact</span><span class="tv or-tv">{{ $publisher->last_contacted_on?->format('M j') ?? '—' }}</span>@php $tries = $publisher->events->whereIn('kind', ['pitched', 'nudged'])->count(); @endphp<span class="sub">{{ $tries }} {{ $tries === 1 ? 'pitch or follow-up' : 'pitches and follow-ups' }}</span></div>
        @if($sales)
            <div class="tile"><span class="tl">Affiliate sales, 12 months</span><span class="tv">{{ Report::money((int) $sales['year']) }}</span><span class="sub">{{ number_format($sales['orders']) }} orders · {{ Report::money((int) $sales['this']) }} this month</span></div>
        @endif
    </section>

    @if($publisher->result_url)
        <p class="or-live">Live: <a href="{{ $publisher->result_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $publisher->result_url), 90) }} ↗</a></p>
    @endif

    @if($publisher->isOpen() && $publisher->events->whereIn('kind', ['pitched', 'nudged'])->count() >= config('outreach.max_nudges') && $publisher->stage === 'contacted')
        <div class="callout warn-callout" role="status"><div>{{ config('outreach.max_nudges') }} tries and no answer. Time to mark it <b>No reply</b> and move on?</div></div>
    @endif

    <div class="aians-split">
        <section>
            <div class="h2row"><h2>Log a step</h2></div>
            <form method="POST" action="{{ route('outreach.act', $publisher) }}" class="card or-act">
                @csrf
                @include('partials.form-errors')
                <label class="fld"><span>What happened <span class="sub">(optional)</span></span><textarea name="body" rows="3" maxlength="1000" placeholder="What you sent or what they said">{{ old('body') }}</textarea></label>
                <div class="or-btns">
                    @if($publisher->isOpen())
                        <button class="btn primary" type="submit" name="action" value="{{ $publisher->stage === 'idea' ? 'pitched' : 'nudged' }}">{{ $publisher->stage === 'idea' ? 'Pitched them' : 'Followed up' }}</button>
                        <button class="btn" type="submit" name="action" value="replied">They replied</button>
                        <button class="btn" type="submit" name="action" value="note">Just a note</button>
                    @else
                        <button class="btn" type="submit" name="action" value="reopen">Reopen</button>
                        <button class="btn" type="submit" name="action" value="note">Add a note</button>
                    @endif
                </div>
                @if($publisher->isOpen())
                    <div class="or-close">
                        <label class="fld grow">Live link <input type="url" name="result_url" maxlength="1024" placeholder="https://… the article, review or post" value="{{ old('result_url') }}"></label>
                        <button class="btn" type="submit" name="action" value="live">It’s live</button>
                    </div>
                    <div class="or-btns">
                        <button class="mini linkish" type="submit" name="action" value="declined">They declined</button>
                        <button class="mini linkish" type="submit" name="action" value="no_reply">No reply, close it</button>
                    </div>
                @endif
            </form>
            @if($publisher->notes)
                <div class="h2row"><h2>Notes</h2></div>
                <div class="card report-box"><p class="or-notes">{{ $publisher->notes }}</p></div>
            @endif
        </section>
        <section>
            <div class="h2row"><h2>History</h2></div>
            @if($publisher->events->isEmpty())
                <div class="empty">Nothing logged yet.</div>
            @else
                <ul class="review-list or-history">
                    @foreach($publisher->events as $e)
                        <li><div class="sub"><b>{{ OutreachEvent::KINDS[$e->kind] ?? $e->kind }}</b> · {{ $e->created_at->format('M j, Y') }} · {{ $e->user?->firstName() ?? 'someone' }}</div>@if($e->body)<p>{{ $e->body }}</p>@endif</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</main>
@endsection
