{{-- Request access to a tool or brand account. Needs $item; $myRequests comes from the page; $return is where to come back to. --}}
@php
    $raKey = \App\Support\Launchpad::typeOf($item).':'.$item->getKey();
    $raLast = isset($myRequests) ? $myRequests->get($raKey) : null;
    $raName = $item instanceof \App\Models\Account ? $item->title() : $item->name;
@endphp
<div class="req">
    @if($raLast?->isOpen())
        <span class="req-state">Requested {{ $raLast->created_at->format('M j') }}</span>
        <form method="POST" action="{{ route('access-requests.destroy', $raLast) }}">@csrf @method('DELETE')
            <input type="hidden" name="return" value="{{ $return ?? 'access' }}">
            <button class="mini linkbtn" type="submit">Withdraw</button>
        </form>
    @else
        @if($raLast?->status === 'granted')
            <span class="req-state ok-t">✓ Granted {{ $raLast->handled_at?->format('M j') }}</span>
        @elseif($raLast?->status === 'declined')
            <span class="req-state warn-t" @if($raLast->decision_note) title="{{ $raLast->decision_note }}" @endif>Declined {{ $raLast->handled_at?->format('M j') }}</span>
        @endif
        <details class="req-d">
            <summary class="mini">{{ $raLast ? 'Ask again' : 'Request access' }}</summary>
            <form method="POST" action="{{ route('access-requests.store') }}" class="req-form">
                @csrf
                <input type="hidden" name="type" value="{{ explode(':', $raKey)[0] }}">
                <input type="hidden" name="id" value="{{ $item->getKey() }}">
                <input type="hidden" name="return" value="{{ $return ?? 'access' }}">
                <label class="sub" for="ra-{{ str_replace(':', '-', $raKey) }}">What do you need it for? (optional)</label>
                <textarea id="ra-{{ str_replace(':', '-', $raKey) }}" name="reason" rows="2" maxlength="500" placeholder="e.g. Running {{ $raName }} this month"></textarea>
                <p class="hint">Never send passwords. An admin will share access through the password manager.</p>
                <button class="btn primary" type="submit">Send request</button>
            </form>
        </details>
    @endif
</div>
