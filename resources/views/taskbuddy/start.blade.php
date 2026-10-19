<form method="POST" action="{{ route('taskbuddy.start') }}" enctype="multipart/form-data" class="card tb-start">
    @csrf
    <label class="tb-f"><b>Paste notes, an email, a brief or a plan</b>
        <textarea name="text" rows="8" placeholder="e.g. Kickoff notes for the Ocusafe winter launch: Leona to update the safety-glasses landing page by Nov 10, Chanley drafts two emails…">{{ old('text') }}</textarea>
    </label>
    <label class="tb-f tb-file"><b>…or upload a file</b>
        <input type="file" name="file" accept="{{ $accept }}">
        <span class="sub">PDF, Word, PowerPoint, Excel, a screenshot or photo (PNG, JPG), or a text, CSV or Markdown file. Up to 15 MB.</span>
    </label>
    <label class="tb-f"><b>Anything TaskBuddy should know?</b> <span class="sub">Optional</span>
        <input type="text" name="note" maxlength="2000" value="{{ old('note') }}" placeholder="e.g. Only the parts about email. Everything should be done before Black Friday.">
    </label>
    <div class="tb-row">
        <label class="tb-f"><span>Tasks are for</span>
            <select name="assignee_id">
                @foreach($people as $p)<option value="{{ $p->id }}" @selected((int) old('assignee_id', auth()->id()) === $p->id)>{{ $p->is(auth()->user()) ? 'Me ('.$p->firstName().')' : $p->name }}</option>@endforeach
            </select>
            <span class="sub">Unless the source says who does what</span>
        </label>
        <label class="tb-f"><span>Brand</span>
            <select name="brand_id">
                <option value="">Work it out from the source</option>
                @foreach($taskBrands as $b)<option value="{{ $b->id }}" @selected((int) old('brand_id') === $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        </label>
        <button class="btn primary" type="submit">Make my task list</button>
    </div>
    <p class="sub">It reads the file and drafts the tasks; nothing is added until you review them. Files are sent to OpenAI to read (nothing is stored there), kept here encrypted only until you add the tasks or for a week, and never shown to anyone else. Don’t upload passwords or customer details.</p>
</form>
