@extends('layouts.app', ['title' => 'Launch templates'])

@section('content')
<main class="wrap aians">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Tasks</span><span>Everyone can use · admins edit</span></div>
            <h1 class="form-h">Launch templates @include('partials.star', ['key' => 'page:21', 'label' => 'Launch templates'])</h1>
            <p class="sub aians-why">Pick a checklist, give it a name, brand and start day, and every step becomes a task on the board, due the right number of days before or after.</p>
        </div>
    </div>

    <section class="tpl-cards">
        @foreach($templates as $t)
            <article class="card tpl-card">
                <h2>{{ $t->name }}</h2>
                @if($t->description)<p class="sub">{{ $t->description }}</p>@endif
                <div class="tpl-foot">
                    <span class="sub">{{ $t->items_count }} {{ Str::plural('step', $t->items_count) }}</span>
                    @can('admin')<a class="mini" href="{{ route('templates.edit', $t) }}">Edit</a>@endcan
                    <a class="btn primary" href="{{ route('templates.run-form', $t) }}">Use this template</a>
                </div>
            </article>
        @endforeach
        @can('admin')
            <form method="POST" action="{{ route('templates.store') }}" class="card tpl-card tpl-new">
                @csrf
                <h2>New template</h2>
                <input type="text" name="name" required maxlength="120" placeholder="e.g. Ocusafe trade show" aria-label="Template name">
                <button class="btn" type="submit">Create and add steps</button>
            </form>
        @endcan
    </section>

    <section>
        <div class="h2row"><h2>Recent launches</h2><span class="sub">Templates already in use, with how many of their tasks are done</span></div>
        @if($runs->isEmpty())
            <div class="empty">None yet. Use a template above to start one.</div>
        @else
            <div class="tablewrap">
                <table class="sales-ch aians-rates">
                    <thead><tr><th>Launch</th><th>Template</th><th>Starts</th><th>Progress</th></tr></thead>
                    <tbody>
                        @foreach($runs as $r)
                            <tr>
                                <td><a href="{{ route('templates.show-run', $r) }}"><b>{{ $r->name }}</b></a>@if($r->brand)<div class="sub">{{ $r->brand->name }}</div>@endif</td>
                                <td>{{ $r->template?->name ?? '—' }}</td>
                                <td>{{ $r->starts_on->format('M j, Y') }}</td>
                                <td><span class="share"><i style="width:{{ $r->tasks_count ? round($r->done_count / $r->tasks_count * 100) : 0 }}%"></i></span><span class="share-t">{{ $r->done_count }} of {{ $r->tasks_count }} done</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>
@endsection
