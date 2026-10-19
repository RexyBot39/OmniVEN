@php
    $draft = $run->draft ?? [];
    $groups = collect($draft)->map(fn ($t, $i) => $t + ['i' => $i])->groupBy(fn ($t) => $t['group'] ?: 'Tasks');
@endphp
@if(! $draft)
    <div class="empty">{{ $run->error ?? 'No tasks were found.' }}</div>
@else
    <form method="POST" action="{{ route('taskbuddy.add', $run) }}" class="tb-review" data-tb-review>
        @csrf
        <div class="h2row"><h2>Review {{ count($draft) }} {{ \Illuminate\Support\Str::plural('task', count($draft)) }}</h2><span class="sub">Edit anything, untick what you don’t want, then add them.</span></div>
        @foreach($groups as $group => $tasks)
            <h3 class="tb-group">{{ $group }}</h3>
            @foreach($tasks as $t)
                @php $n = $t['i']; @endphp
                <article class="card tb-task">
                    <label class="tb-keep"><input type="checkbox" name="tasks[{{ $n }}][keep]" value="1" checked data-tb-keep><span class="sr">Add this task</span></label>
                    <div class="tb-task-body">
                        <input class="tb-title" type="text" name="tasks[{{ $n }}][title]" value="{{ $t['title'] }}" maxlength="200" aria-label="Title">
                        <textarea name="tasks[{{ $n }}][details]" rows="{{ min(8, max(2, substr_count($t['details'], "\n") + 2)) }}" maxlength="4000" aria-label="Details">{{ $t['details'] }}</textarea>
                        <div class="tb-fields">
                            <label><span>For</span><select name="tasks[{{ $n }}][assignee_id]">@foreach($people as $p)<option value="{{ $p->id }}" @selected($t['assignee_id'] === $p->id)>{{ $p->name }}</option>@endforeach</select></label>
                            <label><span>Brand</span><select name="tasks[{{ $n }}][brand_id]"><option value="">None</option>@foreach($taskBrands as $b)<option value="{{ $b->id }}" @selected($t['brand_id'] === $b->id)>{{ $b->name }}</option>@endforeach</select></label>
                            <label><span>Due</span><input type="date" name="tasks[{{ $n }}][due_on]" value="{{ $t['due_on'] }}"></label>
                            @if($campaigns->isNotEmpty())
                                <label><span>Campaign</span><select name="tasks[{{ $n }}][campaign_id]"><option value="">None</option>@foreach($campaigns as $c)<option value="{{ $c->id }}" @selected($t['campaign_id'] === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
                            @endif
                            <label class="tb-hot"><input type="checkbox" name="tasks[{{ $n }}][hot]" value="1" @checked($t['hot'])> Hotlist</label>
                        </div>
                        @if($t['why'])<p class="sub tb-why">From: “{{ $t['why'] }}”</p>@endif
                    </div>
                </article>
            @endforeach
        @endforeach
        <div class="tb-addbar">
            <button class="btn primary" type="submit" data-tb-count>Add {{ count($draft) }} {{ \Illuminate\Support\Str::plural('task', count($draft)) }}</button>
            <span class="sub">They go on the task board for the people chosen, marked as assigned by you.</span>
        </div>
    </form>

    <form method="POST" action="{{ route('taskbuddy.answer', $run) }}" class="card tb-changes">
        @csrf
        <label class="tb-f"><b>Ask TaskBuddy to change the list</b>
            <input type="text" name="feedback" maxlength="2000" placeholder="e.g. Push every date back a week, and give the design tasks to Leona" required>
        </label>
        <div class="tb-row"><button class="btn" type="submit">Revise the list</button><span class="sub">Edits made above are replaced by the revised list.</span></div>
    </form>
@endif
