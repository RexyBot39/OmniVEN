@extends('layouts.app', ['title' => 'Edit '.$template->name])

@section('content')
<main class="wrap aians">
    <a class="back" href="{{ route('templates.index') }}">← Launch templates</a>
    <h1 class="form-h">Edit template</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ route('templates.update', $template) }}" class="card tpl-run">
        @csrf @method('PUT')
        <div class="links-grid">
            <div class="fld"><label for="name">Name</label><input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $template->name) }}"></div>
            <div class="fld span2"><label for="description">What it’s for</label><input id="description" name="description" type="text" maxlength="500" value="{{ old('description', $template->description) }}"></div>
        </div>
        <p class="sub">Days: negative is before the start day, 0 is the start day, positive is after. Write <code>{name}</code> where the launch name should go.</p>
        <div class="tablewrap">
            <table class="sales-ch tpl-steps tpl-edit">
                <thead><tr><th>Days</th><th>Step</th><th>Usual person</th><th>Hotlist</th><th>Remove</th></tr></thead>
                <tbody>
                    @foreach($template->items as $i)
                        <tr>
                            <td><input type="number" name="items[{{ $i->id }}][offset_days]" value="{{ $i->offset_days }}" min="-365" max="365" aria-label="Days from start"></td>
                            <td><input type="text" name="items[{{ $i->id }}][title]" value="{{ $i->title }}" maxlength="200" aria-label="Step"><textarea name="items[{{ $i->id }}][details]" maxlength="2000" rows="1" placeholder="Details (optional)" aria-label="Details">{{ $i->details }}</textarea></td>
                            <td><select name="items[{{ $i->id }}][assignee_id]" aria-label="Usual person"><option value="">Whoever runs it picks</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected($i->assignee_id === $p->id)>{{ $p->name }}</option>@endforeach</select></td>
                            <td><input type="checkbox" name="items[{{ $i->id }}][hot]" value="1" @checked($i->hot) aria-label="Hotlist"></td>
                            <td><input type="checkbox" name="items[{{ $i->id }}][remove]" value="1" aria-label="Remove step"></td>
                        </tr>
                    @endforeach
                    @foreach(range(1, 3) as $n)
                        <tr class="tpl-blank">
                            <td><input type="number" name="items[new{{ $n }}][offset_days]" value="0" min="-365" max="365" aria-label="Days from start"></td>
                            <td><input type="text" name="items[new{{ $n }}][title]" maxlength="200" placeholder="Add a step…" aria-label="New step"><textarea name="items[new{{ $n }}][details]" maxlength="2000" rows="1" placeholder="Details (optional)" aria-label="Details"></textarea></td>
                            <td><select name="items[new{{ $n }}][assignee_id]" aria-label="Usual person"><option value="">Whoever runs it picks</option>@foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></td>
                            <td><input type="checkbox" name="items[new{{ $n }}][hot]" value="1" aria-label="Hotlist"></td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit">Save template</button><span class="sub">Steps are shown in day order after saving. Need more rows? Save, and three new blank rows appear.</span></div>
    </form>
    <form method="POST" action="{{ route('templates.destroy', $template) }}" data-confirm>@csrf @method('DELETE')<button class="mini danger-link" type="submit" data-label="Delete template">Delete this template</button></form>
</main>
@endsection
