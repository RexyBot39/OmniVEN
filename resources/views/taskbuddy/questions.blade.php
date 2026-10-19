<form method="POST" action="{{ route('taskbuddy.answer', $run) }}" class="card tb-questions">
    @csrf
    <h2>A few questions first</h2>
    <p class="sub">Your answers make the list more accurate. Leave any blank and TaskBuddy will use its best judgment.</p>
    @foreach($run->questions ?? [] as $i => $q)
        <fieldset class="tb-q">
            <legend><b>{{ $i + 1 }}. {{ $q['question'] }}</b>@if($q['why'])<span class="sub"> {{ $q['why'] }}</span>@endif</legend>
            @if(! empty($q['choices']))
                <div class="tb-choices">
                    @foreach($q['choices'] as $c)
                        <label class="tb-choice"><input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $c }}"> {{ $c }}</label>
                    @endforeach
                </div>
            @endif
            <input type="text" name="others[{{ $q['id'] }}]" maxlength="500" placeholder="{{ empty($q['choices']) ? 'Your answer' : 'Or type your own answer' }}" aria-label="{{ empty($q['choices']) ? 'Your answer' : 'Your own answer' }}">
        </fieldset>
    @endforeach
    <label class="tb-f"><span>Anything else to change?</span> <input type="text" name="feedback" maxlength="2000" placeholder="Optional, e.g. split the email work into one task per email"></label>
    <div class="tb-row">
        <button class="btn primary" type="submit">Send answers</button>
        <button class="btn" type="submit" name="skip" value="1" formnovalidate>Skip, review the draft ({{ count($run->draft ?? []) }} {{ \Illuminate\Support\Str::plural('task', count($run->draft ?? [])) }})</button>
    </div>
</form>
