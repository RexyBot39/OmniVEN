@extends('layouts.app', ['title' => 'Campaigns'])

@section('content')
<main class="wrap sales outreach">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Campaigns</span><span>Shown on the Action Calendar</span></div>
            <h1 class="form-h">Campaigns @include('partials.star', ['key' => 'page:28', 'label' => 'Campaigns'])</h1>
            <p class="sub">Each campaign gathers its dates, tracked links, tasks and results in one place.</p>
        </div>
        <a class="btn primary" href="{{ route('campaigns.create') }}">+ Plan a campaign</a>
    </div>

    @foreach(['Running now' => $running, 'Coming up' => $upcoming] as $label => $list)
        <section>
            <div class="h2row"><h2>{{ $label }}</h2><span class="sub">{{ $list->count() }}</span></div>
            @if($list->isEmpty())
                <div class="empty">{{ $label === 'Running now' ? 'Nothing running today.' : 'Nothing planned yet.' }}</div>
            @else
                <ul class="or-list">@foreach($list as $c)@include('campaigns.row', ['c' => $c])@endforeach</ul>
            @endif
        </section>
    @endforeach

    @if($untracked->isNotEmpty())
        <section>
            <div class="h2row"><h2>Link tags without a campaign</h2><span class="sub">Used in campaign links in the last 90 days</span></div>
            <ul class="review-list">
                @foreach($untracked as $u)
                    <li><code>{{ $u->campaign }}</code> <span class="sub">· {{ $u->n }} {{ \Illuminate\Support\Str::plural('link', $u->n) }} · first {{ \Carbon\Carbon::parse($u->first_at)->format('M j') }}</span>
                        <a class="mini" href="{{ route('campaigns.create', array_filter(['name' => \Illuminate\Support\Str::headline($u->campaign), 'tag' => $u->campaign, 'brand' => $u->brand_id, 'starts_on' => \Carbon\Carbon::parse($u->first_at)->format('Y-m-d')])) }}">Make it a campaign</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($ended->isNotEmpty())
        <section>
            <div class="h2row"><h2>Ended</h2><span class="sub">Last 90 days</span></div>
            <ul class="or-list">@foreach($ended as $c)@include('campaigns.row', ['c' => $c])@endforeach</ul>
        </section>
    @endif
</main>
@endsection
