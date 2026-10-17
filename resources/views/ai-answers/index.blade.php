@extends('layouts.app', ['title' => 'AI answers'])
@use('App\Support\Revenue\Report')

@section('content')
<main class="wrap aians">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>AI answer tracking</span><span>Checked monthly</span></div>
            <h1 class="form-h">AI answers @include('partials.star', ['key' => 'page:16', 'label' => 'AI answers'])</h1>
            <p class="sub">Questions no assistant mentioned us for go to <a href="{{ route('content-gaps', ['kind' => 'ai']) }}">Content gaps →</a></p>
            <p class="sub aians-why">Shoppers now ask ChatGPT, Google and other assistants where to buy. Each month, ask each one the questions below and record whether the answer recommends us, mentions us, or leaves us out. Use a private or logged-out window so past chats don’t sway the answer.</p>
        </div>
    </div>

    <nav class="weeknav" aria-label="Choose month">
        <a class="btn" href="{{ route('ai-answers', array_filter(['month' => $month->subMonth()->format('Y-m'), 'brand' => $brand?->slug])) }}">← {{ $month->subMonth()->format('F') }}</a>
        <strong>{{ $month->format('F Y') }}</strong>
        @unless($isCurrent)
            <a class="btn" href="{{ route('ai-answers', array_filter(['month' => $month->addMonth()->format('Y-m'), 'brand' => $brand?->slug])) }}">{{ $month->addMonth()->format('F') }} →</a>
            <a class="mini" href="{{ route('ai-answers', array_filter(['brand' => $brand?->slug])) }}">Back to this month</a>
        @endunless
        <span class="sales-brands aians-brands">
            <a href="{{ route('ai-answers', ['month' => $month->format('Y-m')]) }}" @if(! $brand) aria-current="true" @endif>All brands</a>
            @foreach($brands->whereIn('id', \App\Models\AiQuestion::where('active', true)->pluck('brand_id')->unique()) as $b)
                <a href="{{ route('ai-answers', ['month' => $month->format('Y-m'), 'brand' => $b->slug]) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>
            @endforeach
        </span>
    </nav>

    <section class="tiles sales-tiles" aria-label="{{ $month->format('F') }} at a glance">
        <div class="tile"><span class="tl">Mention rate</span><span class="tv">{{ Report::pct($now['mention'], 0) }}</span>@include('partials.delta-month', ['now' => $now['mention'], 'then' => $prev['mention']])</div>
        <div class="tile"><span class="tl">Recommended us</span><span class="tv">{{ Report::pct($now['recommend'], 0) }}</span>@include('partials.delta-month', ['now' => $now['recommend'], 'then' => $prev['recommend']])</div>
        <div class="tile"><span class="tl">Answers recorded</span><span class="tv">{{ $now['checked'] }}<small> of {{ $questions->count() * count($engines) }}</small></span></div>
    </section>

    <div class="aians-split">
        <section>
            <div class="h2row"><h2>By assistant</h2><span class="sub">{{ $month->format('F') }}</span></div>
            <div class="tablewrap">
                <table class="sales-ch aians-rates">
                    <thead><tr><th>Assistant</th><th>Mention rate</th><th class="n">Recommended</th><th class="n">Answers</th></tr></thead>
                    <tbody>
                        @foreach($engines as $key => $e)
                            <tr>
                                <td><b>{{ $e['label'] }}</b></td>
                                <td><span class="share"><i style="width:{{ round(($perEngine[$key]['mention'] ?? 0) * 100) }}%"></i></span><span class="share-t">{{ Report::pct($perEngine[$key]['mention'], 0) }}</span></td>
                                <td class="n">{{ Report::pct($perEngine[$key]['recommend'], 0) }}</td>
                                <td class="n">{{ $perEngine[$key]['checked'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section>
            <div class="h2row"><h2>Last six months</h2><span class="sub">Mention rate, all assistants</span></div>
            <div class="tablewrap">
                <table class="sales-ch aians-rates">
                    <thead><tr><th>Month</th><th>Mention rate</th><th class="n">Recommended</th><th class="n">Answers</th></tr></thead>
                    <tbody>
                        @foreach(array_reverse($trend) as $t)
                            <tr>
                                <td>{{ $t['month']->format('M Y') }}</td>
                                <td><span class="share"><i style="width:{{ round(($t['mention'] ?? 0) * 100) }}%"></i></span><span class="share-t">{{ Report::pct($t['mention'], 0) }}</span></td>
                                <td class="n">{{ Report::pct($t['recommend'], 0) }}</td>
                                <td class="n">{{ $t['checked'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section>
        <div class="h2row"><h2>{{ $month->format('F') }} answers</h2><span class="sub">Press <b>Ask ↗</b> to open the assistant with the question typed in, then pick what it said.</span></div>
        @if($questions->isEmpty())
            <div class="empty">No questions yet.@can('admin') Add some below.@endcan</div>
        @else
            <form method="POST" action="{{ route('ai-answers.save') }}" class="aians-form">
                @csrf
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                @if($brand)<input type="hidden" name="brand" value="{{ $brand->slug }}">@endif
                <div class="tablewrap">
                    <table class="aians-grid">
                        <thead><tr><th>Question</th>@foreach($engines as $e)<th>{{ $e['label'] }}</th>@endforeach</tr></thead>
                        @foreach($groups as $group => $qs)
                            <tbody>
                                <tr class="aians-group"><th colspan="{{ count($engines) + 1 }}">{{ $group }}</th></tr>
                                @foreach($qs as $q)
                                    <tr>
                                        <td class="aians-q">
                                            <span>{{ $q->question }}</span>
                                            <input type="text" name="competitors[{{ $q->id }}]" maxlength="300" placeholder="Who did they name instead?" aria-label="Brands named instead, for: {{ $q->question }}" value="{{ $grid->get($q->id)?->first()?->competitors }}">
                                        </td>
                                        @foreach($engines as $key => $e)
                                            <td class="aians-cell r-{{ $grid[$q->id][$key]->result ?? 'none' }}">
                                                <select name="results[{{ $q->id }}][{{ $key }}]" aria-label="{{ $e['label'] }}: {{ $q->question }}" data-aians>
                                                    <option value="">—</option>
                                                    @foreach($results as $rk => $rl)<option value="{{ $rk }}" @selected(($grid[$q->id][$key]->result ?? null) === $rk)>{{ $rl }}</option>@endforeach
                                                </select>
                                                @if($q->askUrl($key))<a class="mini" href="{{ $q->askUrl($key) }}" target="_blank" rel="noopener noreferrer" title="{{ $e['ask'] ? 'Opens '.$e['label'].' with the question typed in' : 'Opens '.$e['label'].'; paste the question' }}">{{ $e['ask'] ? 'Ask ↗' : 'Open ↗' }}</a>@endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
                <div class="form-actions aians-save"><button class="btn primary" type="submit">Save answers</button><span class="sub">Saving keeps who recorded each answer. Leave a cell on — if you didn’t check it.</span></div>
            </form>
        @endif
    </section>

    @can('admin')
        <section id="questions">
            <div class="h2row"><h2>Questions</h2><span class="sub">Write them the way a shopper would ask, without our brand name, unless the question is about us.</span></div>
            <form method="POST" action="{{ route('ai-answers.questions.store') }}" class="card aians-add">
                @csrf
                <input type="text" name="question" required maxlength="300" placeholder="e.g. Where can I buy prescription sunglasses online?" aria-label="New question">
                <select name="brand_id" aria-label="Brand"><option value="">Any brand</option>@foreach($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                <button class="btn" type="submit">Add question</button>
            </form>
            <ul class="aians-qlist">
                @foreach($allQuestions as $q)
                    <li class="{{ $q->active ? '' : 'off' }}">
                        <form method="POST" action="{{ route('ai-answers.questions.update', $q) }}" class="aians-qrow">
                            @csrf @method('PATCH')
                            <input type="text" name="question" required maxlength="300" value="{{ $q->question }}" aria-label="Question">
                            <select name="brand_id" aria-label="Brand"><option value="">Any brand</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected($q->brand_id === $b->id)>{{ $b->name }}</option>@endforeach</select>
                            <label class="inline"><input type="checkbox" name="active" value="1" @checked($q->active)> Ask each month</label>
                            <button class="btn" type="submit">Save</button>
                        </form>
                        <form method="POST" action="{{ route('ai-answers.questions.destroy', $q) }}" data-confirm>@csrf @method('DELETE')<button class="mini danger-link" type="submit" data-label="Delete">Delete</button></form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endcan
</main>
@endsection
