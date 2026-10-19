{{-- One question or answer. Pending answers are checked again by omniven.js until they're ready. --}}
@if($m->role === 'user')
    <div class="as-msg as-q" id="m-{{ $m->id }}"><div class="as-who">You</div><div class="as-body">{!! nl2br(e($m->content)) !!}</div></div>
@elseif($m->status === 'pending')
    <div class="as-msg as-a pending" id="m-{{ $m->id }}" data-poll="{{ route('assistant.poll', $m) }}" aria-live="polite">
        <div class="as-who">OmniVEN</div>
        <div class="as-body"><p class="as-thinking"><span class="as-dots" aria-hidden="true"><i></i><i></i><i></i></span> Looking through OmniVEN and thinking it through. Deep answers can take a minute or two; this page updates by itself.</p></div>
    </div>
@elseif($m->status === 'error')
    <div class="as-msg as-a err" id="m-{{ $m->id }}" aria-live="polite"><div class="as-who">OmniVEN</div><div class="as-body"><p><b>Couldn’t answer.</b> {{ $m->error }}</p></div></div>
@else
    <div class="as-msg as-a" id="m-{{ $m->id }}" aria-live="polite">
        <div class="as-who">OmniVEN</div>
        <div class="as-body as-md">{!! $m->html() !!}</div>
        <div class="as-meta sub">
            @if($m->tools)Looked at: {{ implode(', ', array_map(fn ($t) => str_replace('_', ' ', $t), $m->tools)) }}@endif
            @if(! empty($isAdmin) && ($m->input_tokens || $m->output_tokens)) · {{ number_format($m->input_tokens + $m->output_tokens) }} tokens · about ${{ number_format($m->input_tokens / 1e6 * config('assistant.price.input') + $m->output_tokens / 1e6 * config('assistant.price.output'), 2) }}@endif
            <button type="button" class="mini linkish" data-copy-answer="#m-{{ $m->id }} .as-md">Copy answer</button>
        </div>
    </div>
@endif
