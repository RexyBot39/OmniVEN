{{-- ================= TASKS ================= --}}
<section data-view="tasks" hidden>
    <div class="h2row">
        <h2>Tasks</h2>
        <span class="sub">Assigned work. Flag anything urgent for the Hotlist.</span>
        <div class="seg" role="group" aria-label="Show">
            <button type="button" class="seg-b" data-taskfilter="mine" aria-pressed="false">Mine</button>
            <button type="button" class="seg-b" data-taskfilter="all" aria-pressed="true">Everyone</button>
        </div>
        <a class="btn primary" href="{{ route('work.create', ['type' => 'task']) }}">{{ auth()->user()->can('admin') ? '+ New task or routine' : '+ New task' }}</a>
    </div>

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
