{{-- Admins: open access requests, oldest first. --}}
@if($openRequests->isNotEmpty())
    <div class="req-panel" id="access-requests">
        <div class="h2row"><h2>Access requests</h2><span class="sub">{{ $openRequests->count() }} waiting · share logins through the password manager, then mark granted</span></div>
        <ul class="req-list">
            @foreach($openRequests as $r)
                <li>
                    <div class="req-who">
                        <b>{{ $r->user?->name ?? 'Someone' }}</b> wants <b>{{ $r->itemName() }}</b>
                        <span class="sub">{{ $r->created_at->format('M j, g:ia') }}</span>
                        @if($r->reason)<p class="req-reason">“{{ $r->reason }}”</p>@endif
                    </div>
                    <div class="req-acts">
                        <form method="POST" action="{{ route('access-requests.grant', $r) }}">@csrf<button class="btn primary" type="submit">Granted</button></form>
                        <details class="req-d">
                            <summary class="mini">Decline</summary>
                            <form method="POST" action="{{ route('access-requests.decline', $r) }}" class="req-form">
                                @csrf
                                <label class="sub" for="dn-{{ $r->id }}">Note for {{ $r->user?->firstName() ?? 'them' }} (optional)</label>
                                <input id="dn-{{ $r->id }}" name="note" type="text" maxlength="300">
                                <button class="btn" type="submit">Decline request</button>
                            </form>
                        </details>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
