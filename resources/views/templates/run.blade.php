@extends('layouts.app', ['title' => 'Use '.$template->name])

@section('content')
<main class="wrap aians">
    <a class="back" href="{{ route('templates.index') }}">← Launch templates</a>
    <h1 class="form-h">{{ $template->name }}</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ route('templates.run', $template) }}" class="card tpl-run" id="tpl-run" data-steps="{{ $template->items->map(fn ($i) => ['id' => $i->id, 'offset' => $i->offset_days])->toJson() }}">
        @csrf
        <div class="links-grid">
            <div class="fld"><label for="name">Name</label><input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $template->name.' '.now()->year) }}"><span class="hint">Used in the task titles, e.g. “Build the Black Friday 2026 email”.</span></div>
            <div class="fld"><label for="brand_id">Brand</label><select id="brand_id" name="brand_id"><option value="">No brand</option>@foreach($taskBrands as $b)<option value="{{ $b->id }}" @selected((string) old('brand_id') === (string) $b->id)>{{ $b->name }}</option>@endforeach</select></div>
            <div class="fld"><label for="starts_on">Start day</label><input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on', $defaultStart) }}"><span class="hint">Launch or sale day. Steps are due before or after it.</span></div>
            <div class="fld"><label for="campaign_id">Campaign <span class="hint">(optional)</span></label><select id="campaign_id" name="campaign_id"><option value="">None</option>@foreach($campaigns as $c)<option value="{{ $c->id }}" @selected((string) old('campaign_id', $campaign?->id) === (string) $c->id)>{{ $c->name }} · {{ $c->dateLabel() }}</option>@endforeach</select><span class="hint">Its tasks then show on the campaign and the Action Calendar.</span></div>
            <div class="fld"><label for="assignee_id">Everything else goes to</label><select id="assignee_id" name="assignee_id" required>@foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('assignee_id', auth()->id()) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        </div>

        <div class="tablewrap">
            <table class="sales-ch tpl-steps">
                <thead><tr><th>Step</th><th>When</th><th>Due</th><th>Who</th><th>Skip</th></tr></thead>
                <tbody>
                    @foreach($template->items as $i)
                        <tr>
                            <td><b data-tpl="{{ $i->title }}">{{ $i->title }}</b>@if($i->hot) <span class="pill hot">Hotlist</span>@endif @if($i->details)<div class="sub">{{ \Illuminate\Support\Str::limit($i->details, 140) }}</div>@endif</td>
                            <td class="sub">{{ $i->whenLabel() }}</td>
                            <td class="tpl-due" data-due="{{ $i->id }}">—</td>
                            <td><select name="assignees[{{ $i->id }}]" aria-label="Who: {{ $i->title }}"><option value="">{{ $i->assignee ? $i->assignee->firstName().' (usual)' : 'Same as above' }}</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('assignees.'.$i->id) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select></td>
                            <td><input type="checkbox" name="skip[{{ $i->id }}]" value="1" aria-label="Skip: {{ $i->title }}" @checked(old('skip.'.$i->id))></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit">Create {{ $template->items->count() }} tasks</button><span class="sub">“{name}” in a step is replaced with the name above. Everything can be edited afterwards on the board.</span></div>
    </form>
</main>
@endsection
