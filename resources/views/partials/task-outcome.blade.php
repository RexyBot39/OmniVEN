{{-- A finished task's result: the note and link, or (for whoever can tick it) a box to add them. --}}
@php
    $justDone = session('outcome_for') === $t->id || (int) old('outcome_task') === $t->id;
    $canEdit = auth()->user()->can('complete-task', $t);
@endphp
@if($t->outcome || $t->outcome_url || $canEdit)
    <div class="outcome {{ $t->outcome || $t->outcome_url ? '' : 'empty' }}">
        @if($t->outcome || $t->outcome_url)
            <span class="outcome-v">→ {{ $t->outcome ?? '' }}@if($t->outcome_url) <a href="{{ $t->outcome_url }}" target="_blank" rel="noopener noreferrer">{{ $t->outcome ? 'Open' : Str::limit(preg_replace('#^https?://(www\.)?#', '', $t->outcome_url), 60) }}</a>@endif</span>
        @endif
        @if($canEdit)
            <details class="outcome-f" @if($justDone) open @endif>
                <summary>{{ $t->outcome || $t->outcome_url ? 'Edit result' : 'Add result' }}</summary>
                <form method="POST" action="{{ route('tasks.outcome', $t) }}">
                    @csrf
                    <input type="hidden" name="outcome_task" value="{{ $t->id }}">
                    <label>Link <input type="url" name="outcome_url" maxlength="1024" placeholder="https://… the post, page, email or listing" value="{{ (int) old('outcome_task') === $t->id ? old('outcome_url') : $t->outcome_url }}"></label>
                    <label>What came of it <input type="text" name="outcome" maxlength="300" placeholder="e.g. Blue light guide live, 3 affiliate links" value="{{ (int) old('outcome_task') === $t->id ? old('outcome') : $t->outcome }}"></label>
                    @if((int) old('outcome_task') === $t->id && $errors->hasAny(['outcome', 'outcome_url']))<p class="err">{{ $errors->first('outcome_url') ?: $errors->first('outcome') }}</p>@endif
                    <button class="btn" type="submit">Save</button>
                </form>
            </details>
        @endif
    </div>
@endif
