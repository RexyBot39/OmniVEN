@extends('layouts.app', ['title' => 'New Metabase query'])
@use('App\Support\Metabase\Result')

@section('content')
<main class="wrap sales mb">
    @include('metabase._head', ['title' => 'New query', 'lede' => 'Ask the data a question: write SQL, or pick a table and what to count. Run it to see the answer; save it to share it in Metabase.'])

    @if(\App\Support\Metabase\Metabase::configured() && ! $error)
        @php
            $mode = $mode ?? 'sql';
            $tableRow = collect($tables)->firstWhere('id', $table ?? null);
            $fieldsOf = fn ($kind = null) => collect($tableRow['fields'] ?? [])->when($kind, fn ($c) => $c->where('kind', $kind))->values();
            $display = $display ?? 'auto';
        @endphp
        <nav class="seo-tabs" aria-label="How to write it">
            <a href="{{ route('metabase.query', ['db' => $db]) }}" @if($mode === 'sql') aria-current="page" @endif>SQL</a>
            <a href="{{ route('metabase.query', ['mode' => 'simple', 'db' => $db]) }}" @if($mode === 'simple') aria-current="page" @endif>Pick a table</a>
        </nav>

        @if($mode === 'simple')
            <form method="GET" action="{{ route('metabase.query') }}" class="mb-pick">
                <input type="hidden" name="mode" value="simple">
                <label>Database <select name="db">@foreach($databases as $d)<option value="{{ $d['id'] }}" @selected($db === $d['id'])>{{ $d['name'] }}</option>@endforeach</select></label>
                <label>Table <select name="table"><option value="">Choose…</option>@foreach($tables as $t)<option value="{{ $t['id'] }}" @selected(($table ?? null) === $t['id'])>{{ $t['name'] }}</option>@endforeach</select></label>
                <button class="btn" type="submit">{{ $tableRow ? 'Change' : 'Next' }}</button>
            </form>
        @endif

        @if($mode === 'sql' || $tableRow)
            <form method="POST" action="{{ route('metabase.run') }}" class="mb-form">@csrf
                <input type="hidden" name="mode" value="{{ $mode }}">
                @if($mode === 'sql')
                    <label class="mb-db">Database <select name="db">@foreach($databases as $d)<option value="{{ $d['id'] }}" @selected($db === $d['id'])>{{ $d['name'] }}{{ $d['engine'] ? ' ('.$d['engine'].')' : '' }}</option>@endforeach</select></label>
                    <label for="mb-sql" class="sr">SQL</label>
                    <textarea id="mb-sql" name="sql" rows="10" spellcheck="false" required class="mb-sql" placeholder="SELECT date_trunc('month', created_at) AS month, count(*) AS orders&#10;FROM orders&#10;GROUP BY 1&#10;ORDER BY 1">{{ $sql ?? '' }}</textarea>
                    <p class="sub">Only queries that read data run here (SELECT or WITH, one at a time). Put a date or category first and a number second to get a chart.</p>
                @else
                    <input type="hidden" name="db" value="{{ $db }}"><input type="hidden" name="table" value="{{ $tableRow['id'] }}">
                    <div class="mb-builder">
                        <label>Show
                            <select name="measure">
                                <option value="count">Number of rows</option>
                                @foreach($fieldsOf('number') as $f)
                                    @foreach(['sum' => 'Total', 'avg' => 'Average', 'max' => 'Highest', 'min' => 'Lowest'] as $agg => $word)
                                        <option value="{{ $agg }}:{{ $f['id'] }}" @selected(($measure ?? '') === $agg.':'.$f['id'])>{{ $word }} {{ $f['name'] }}</option>
                                    @endforeach
                                @endforeach
                                @foreach($fieldsOf('text') as $f)<option value="distinct:{{ $f['id'] }}" @selected(($measure ?? '') === 'distinct:'.$f['id'])>Different {{ $f['name'] }} values</option>@endforeach
                            </select>
                        </label>
                        <label>Grouped by
                            <select name="group"><option value="">Nothing (one total)</option>@foreach($fieldsOf() as $f)<option value="{{ $f['id'] }}" @selected(($group ?? null) === $f['id'])>{{ $f['name'] }}</option>@endforeach</select>
                        </label>
                        <label>Dates by
                            <select name="unit">@foreach($units as $k => $label)<option value="{{ $k }}" @selected(($unit ?? 'month') === $k)>{{ $label }}</option>@endforeach</select>
                        </label>
                        @if($fieldsOf('date')->isNotEmpty())
                            <label>Only the last
                                <span class="mb-days"><input type="number" name="days" min="1" max="3650" value="{{ $days ?? '' }}" placeholder="all"> days of
                                <select name="date_field">@foreach($fieldsOf('date') as $f)<option value="{{ $f['id'] }}" @selected(($date_field ?? null) === $f['id'])>{{ $f['name'] }}</option>@endforeach</select></span>
                            </label>
                        @endif
                        <label>Up to <input type="number" name="limit" min="1" max="2000" value="{{ $limit ?? 100 }}"> rows</label>
                    </div>
                @endif
                <div class="mb-run">
                    <label>Show as <select name="display">@foreach(Result::DISPLAYS as $k => $label)<option value="{{ $k }}" @selected($display === $k)>{{ $label }}</option>@endforeach</select></label>
                    <button class="btn primary" type="submit">Run</button>
                    <details class="mb-save">
                        <summary class="btn">Save to Metabase…</summary>
                        <div class="mb-save-f">
                            <label>Name <input type="text" name="name" maxlength="200" value="{{ old('name') }}" placeholder="e.g. Orders by month"></label>
                            <label>Collection <select name="collection_id"><option value="">Our analytics (top level)</option>@foreach($collections as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach</select></label>
                            <button class="btn primary" type="submit" formaction="{{ route('metabase.save') }}">Save question</button>
                        </div>
                    </details>
                </div>
            </form>
        @endif

        @if($result)
            <section>
                <div class="h2row"><h2>Answer</h2>@if($result->ok())<span class="sub">{{ number_format($result->count) }} {{ $result->count === 1 ? 'row' : 'rows' }} · not saved in OmniVEN</span>@endif</div>
                @include('metabase._result', ['res' => $result, 'as' => $display, 'name' => null])
            </section>
        @endif
    @endif
</main>
@endsection
