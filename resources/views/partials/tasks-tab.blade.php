{{-- ================= TASKS ================= --}}
<section data-view="tasks" hidden>
    <div class="h2row">
        <h2>Tasks</h2>
        <span class="sub">Assigned work. Flag anything urgent for the Hotlist.</span>
        <div class="seg" role="group" aria-label="Show">
            <button type="button" class="seg-b" data-taskfilter="mine" aria-pressed="false">Mine</button>
            <button type="button" class="seg-b" data-taskfilter="all" aria-pressed="true">Everyone</button>
        </div>
    </div>

    @can('admin')
        <form method="POST" action="{{ route('tasks.store') }}" class="card task-form">
            @csrf
            <div class="task-form-row">
                <div class="fld grow"><label for="t-title">Task</label><input id="t-title" name="title" type="text" required maxlength="200" placeholder="e.g. Fix Merchant Center disapprovals for Ocusafe"></div>
                <div class="fld"><label for="t-assignee">Assign to</label>
                    <select id="t-assignee" name="assignee_id" required>
                        <option value="" disabled selected>Choose a person</option>
                        @foreach($people as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}{{ $p->is_admin ? ' (admin)' : '' }}{{ $p->is(auth()->user()) ? ' — me' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fld"><label for="t-due">Due</label><input id="t-due" name="due_on" type="date"></div>
            </div>
            <div class="fld"><label for="t-details">Details <span class="hint">(optional)</span></label><textarea id="t-details" name="details" rows="2" maxlength="4000"></textarea></div>
            <div class="form-actions">
                <label class="inline"><input type="checkbox" name="hot" value="1" checked> Add to Hotlist</label>
                <span class="spacer"></span>
                <a class="mini" href="{{ route('people.index') }}">Someone missing? Add them on People</a>
                <button class="btn primary" type="submit">Assign task</button>
            </div>
        </form>
    @endcan

    <div class="task-cols">
        <div class="rcol">
            <h3>Open<small>{{ $openTasks->count() }}</small></h3>
            @forelse($openTasks as $t)
                @include('partials.task-row', ['t' => $t])
            @empty
                <div class="r"><span></span><span class="sub">No open tasks. {{ auth()->user()->is_admin ? 'Assign one above.' : '' }}</span></div>
            @endforelse
            <div class="r task-none" hidden><span></span><span class="sub">Nothing assigned to you.</span></div>
        </div>
        <div class="rcol">
            <h3>Done in the last 14 days<small>{{ $doneTasks->count() }}</small></h3>
            @forelse($doneTasks as $t)
                @include('partials.task-row', ['t' => $t])
            @empty
                <div class="r"><span></span><span class="sub">Finished tasks show here.</span></div>
            @endforelse
        </div>
    </div>
</section>
