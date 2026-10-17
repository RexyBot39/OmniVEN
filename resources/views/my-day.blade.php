@extends('layouts.app', ['title' => 'My morning summary'])

@section('content')
<main class="wrap aians">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Slack</span><span>Weekdays at 8:30 (Eastern)</span></div>
            <h1 class="form-h">My morning summary</h1>
            <p class="sub aians-why">Each weekday morning OmniVEN sends you a Slack message with your Hotlist, anything overdue or due today, and your routines that are due. Days with nothing on your plate are skipped. Here’s what it would say right now.</p>
        </div>
    </div>
    @include('partials.form-errors')

    <div class="myday-actions">
        <form method="POST" action="{{ route('my-day.toggle') }}">@csrf
            <button class="seg-b solo" type="submit" aria-pressed="{{ ($me->myday_slack ?? true) ? 'true' : 'false' }}">{{ ($me->myday_slack ?? true) ? '● On: turn off' : '○ Off: turn on' }}</button>
        </form>
        @if($slack)
            <form method="POST" action="{{ route('my-day.send') }}">@csrf<button class="btn" type="submit">Send it to me now</button></form>
        @else
            <span class="sub">Slack isn’t connected yet, so nothing is sent.</span>
        @endif
    </div>

    <section class="card myday-preview" aria-label="Preview">
        <div class="myday-from"><b>OmniVEN</b> <span class="sub">APP · 8:30 AM</span></div>
        @if($empty)
            <p class="sub">Nothing is due for you today, so no message would be sent.</p>
        @endif
        <div class="myday-text">{!! nl2br(preg_replace(['/\*([^*\n]+)\*/', '/_(\([^\n]*?\))_/'], ['<b>$1</b>', '<i class="sub">$1</i>'], e(html_entity_decode(preg_replace('/<([^|>]+)\|([^>]+)>/', '$2', $text), ENT_QUOTES)))) !!}</div>
    </section>
</main>
@endsection
