{{-- PulseToPost status, read on the server and cached. Every value is escaped; a null figure shows as a gap. --}}
<section class="p2p wrap" aria-label="PulseToPost">
    <div class="p2p-in {{ $pulse['ok'] ? '' : 'down' }}">
        <a class="p2p-name" href="{{ $pulse['dashboard'] }}" target="_blank" rel="noopener noreferrer">{{ \App\Support\BrandIcon::html('pulsetopost', 'xs') }}PulseToPost</a>
        @if($pulse['ok'])
            <div class="p2p-figs">
                <div class="p2p-fig head">
                    @if($pulse['links']['review_queue'] && $pulse['awaiting_review'] !== null)
                        <a href="{{ $pulse['links']['review_queue'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $pulse['awaiting_review'] }} awaiting review: open the review queue"><b>{{ $pulse['awaiting_review'] }}</b></a>
                    @else
                        <b>{{ $pulse['awaiting_review'] ?? '—' }}</b>
                    @endif
                    <span>awaiting review</span>
                </div>
                <div class="p2p-fig {{ $pulse['gated'] ? 'gated' : '' }}"><b>{{ $pulse['gated'] ?? '—' }}</b><span>gated</span></div>
                <div class="p2p-fig">
                    @if($pulse['links']['drafts'] && $pulse['drafts'] !== null)<a href="{{ $pulse['links']['drafts'] }}" target="_blank" rel="noopener noreferrer"><b>{{ $pulse['drafts'] }}</b></a>@else<b>{{ $pulse['drafts'] ?? '—' }}</b>@endif
                    <span>drafts to review</span>
                </div>
                <div class="p2p-fig"><b>{{ $pulse['spend'] ?? '—' }}</b><span>spend this month</span></div>
            </div>
            <p class="p2p-status">
                <span class="p2p-run {{ $pulse['tone'] }}">{{ $pulse['status'] }}</span>
                @if($pulse['queue'])<span>{{ $pulse['queue'] }}</span>@endif
                @if($pulse['auto_disabled'])<span class="p2p-warn">{{ $pulse['auto_disabled'] }} {{ Str::plural('query', $pulse['auto_disabled']) }} auto-disabled</span>@endif
                <span class="sub">as of {{ $pulse['as_of']->format('M j, g:i A') }}</span>
            </p>
        @else
            <p class="p2p-status"><span class="p2p-run bad">Status unavailable</span><span class="sub">{{ $pulse['last_good'] ? 'Last good read '.$pulse['last_good']->format('M j, g:i A') : 'No good read yet' }}</span></p>
        @endif
    </div>
</section>
