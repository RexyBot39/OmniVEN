@extends('layouts.app', ['title' => 'Edit task'])

@section('content')
<main class="wrap formpage">
    <a href="{{ route('dashboard') }}#tasks" class="back">← Back to OmniVEN</a>
    <h1 class="form-h">Edit task</h1>
    @include('partials.form-errors')
    <form method="POST" action="{{ route('tasks.update', $task) }}" class="card form">
        @csrf @method('PUT')
        <div class="fld"><label for="title">Task</label><input id="title" name="title" type="text" required maxlength="200" value="{{ old('title', $task->title) }}"></div>
        <div class="two">
            <div class="fld"><label for="assignee_id">Assigned to</label>
                <select id="assignee_id" name="assignee_id" required>
                    @foreach($people as $p)
                        <option value="{{ $p->id }}" @selected(old('assignee_id', $task->assignee_id) == $p->id)>{{ $p->name }}{{ $p->is_admin ? ' (admin)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fld"><label for="due_on">Due</label><input id="due_on" name="due_on" type="date" value="{{ old('due_on', $task->due_on?->format('Y-m-d')) }}"></div>
        </div>
        <div class="fld"><label for="details">Details</label><textarea id="details" name="details" maxlength="4000" rows="4">{{ old('details', $task->details) }}</textarea></div>
        <label class="inline"><input type="checkbox" name="hot" value="1" @checked(old('hot', $task->hot))> On the Hotlist</label>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ route('dashboard') }}#tasks">Cancel</a><button class="btn primary" type="submit">Save task</button></div>
    </form>
    @include('partials.delete', ['action' => route('tasks.destroy', $task), 'label' => 'Delete this task'])
</main>
@endsection
