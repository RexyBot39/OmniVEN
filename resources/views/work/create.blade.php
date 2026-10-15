@extends('layouts.app', ['title' => auth()->user()->can('admin') ? 'New routine or task' : 'New task'])

@php
    $isAdmin = auth()->user()->can('admin');
    $kind = old('kind', $kind);
    $back = $fromPerson ? route('people.show', $assignee) : route('dashboard').($kind === 'routine' ? '#routines' : '#tasks');
    $who = (string) old('assignee_id', $assignee?->id);
@endphp

@section('content')
<main class="wrap formpage">
    <a href="{{ $back }}" class="back">← {{ $fromPerson ? $assignee->name : 'Back to OmniVEN' }}</a>
    <h1 class="form-h">{{ $isAdmin ? 'New routine or task' : 'New task' }}@include('partials.star', ['key' => 'page:10', 'label' => 'the New task form'])</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ route('work.store') }}" class="card form work-form" id="work-form">
        @csrf
        @if($fromPerson)<input type="hidden" name="back_to_person" value="1">@endif

        @if($isAdmin)
        <fieldset class="fld kind-pick">
            <legend class="lab">What is it?</legend>
            <div class="kind-opts">
                <label class="kind-opt">
                    <input type="radio" name="kind" value="task" @checked($kind === 'task')>
                    <span><b>Task</b><small>One-off work for one person, with a due date. Done once.</small></span>
                </label>
                <label class="kind-opt">
                    <input type="radio" name="kind" value="routine" @checked($kind === 'routine')>
                    <span><b>Routine</b><small>Repeats daily, weekly, monthly or quarterly. The tick resets each time.</small></span>
                </label>
            </div>
        </fieldset>
        @else
        {{-- Routines are added by admins; everyone else adds tasks. --}}
        <input type="hidden" name="kind" value="task">
        @endif

        <div class="fld"><label for="title">What needs doing</label><input id="title" name="title" type="text" required maxlength="200" value="{{ old('title') }}" placeholder="e.g. Fix Merchant Center disapprovals for Ocusafe"></div>

        <div class="two">
            <div class="fld"><label for="assignee_id">Assigned to</label>
                <select id="assignee_id" name="assignee_id" @unless($isAdmin) required @endunless>
                    <option value="" data-team @selected($who === '') @unless($isAdmin) disabled @endunless>{{ $isAdmin ? 'Whole team (routines only)' : 'Choose a person' }}</option>
                    @foreach($people as $p)
                        <option value="{{ $p->id }}" @selected($who === (string) $p->id)>{{ $p->name }}{{ $p->is_admin ? ' (admin)' : '' }}{{ $p->is(auth()->user()) ? ' — me' : '' }}</option>
                    @endforeach
                </select>
                @if($isAdmin)<span class="hint" data-kind="routine">Anyone can still tick an assigned routine to cover for someone.</span>@endif
            </div>
            <div class="fld" data-kind="task"><label for="due_on">Due date @if($isAdmin)<span class="hint">(task)</span>@endif</label><input id="due_on" name="due_on" type="date" value="{{ old('due_on') }}" min="{{ today()->format('Y-m-d') }}"></div>
            @if($isAdmin)<div class="fld" data-kind="routine"><label for="cadence">How often <span class="hint">(routine)</span></label>
                <select id="cadence" name="cadence">@foreach(config('omniven.cadences') as $k => $v)<option value="{{ $k }}" @selected(old('cadence', $routine->cadence) === $k)>{{ $v }}</option>@endforeach</select>
            </div>@endif
        </div>

        <div class="fld"><label for="details">Details <span class="hint">(optional)</span></label><textarea id="details" name="details" maxlength="4000" rows="3" placeholder="Write it as the first step: “Open Merchant Center, fix disapprovals.”">{{ old('details') }}</textarea></div>

        @if($isAdmin)<div class="fld" data-kind="routine"><label for="link">Link to open <span class="hint">(routine)</span></label><input id="link" name="link" type="url" placeholder="https://" maxlength="1024" value="{{ old('link') }}"></div>@endif
        @if($isAdmin)<div data-kind="routine">@include('partials.brand-checks', ['item' => $routine])</div>@endif
        @if($isAdmin)<div class="fld narrow" data-kind="routine"><label for="sort">Sort order <span class="hint">(routine)</span></label><input id="sort" name="sort" type="number" min="0" max="9999" value="{{ old('sort', $routine->sort) }}"></div>@endif

        <div class="form-actions">
            <label class="inline" data-kind="task"><input type="checkbox" name="hot" value="1" @checked(old('_token') ? old('hot') : $isAdmin)> Add to Hotlist @if($isAdmin)<span class="hint">(task)</span>@endif</label>
            <span class="spacer"></span>
            @if($isAdmin)<a class="mini" href="{{ route('people.index') }}">Someone missing? Add them on People</a>@endif
            <a class="btn" href="{{ $back }}">Cancel</a>
            <button class="btn primary" type="submit" data-label-task="Assign task" data-label-routine="Add routine">{{ $kind === 'routine' ? 'Add routine' : 'Assign task' }}</button>
        </div>
    </form>
</main>
@endsection
