@if($state === 'unconfigured')
    <div class="empty slack-setup">
        <strong>Slack isn't connected yet.</strong>
        @can('admin')
            Create the OmniVEN Slack app and add its bot token as <code>SLACK_BOT_TOKEN</code> in Railway. The README has the steps.
        @else
            Ask an admin to connect it.
        @endcan
    </div>
@else
    <div class="slack-bar">
        <div class="slack-channels" role="tablist" aria-label="Slack channels">
            @forelse($channels as $c)
                <button type="button" class="chip" data-slack-channel="{{ $c->channel_id }}" aria-pressed="{{ $current && $current->is($c) ? 'true' : 'false' }}">#{{ $c->name }}</button>
            @empty
                <span class="sub">No channels yet.{{ auth()->user()->is_admin ? ' Add one below.' : ' Ask an admin to add one.' }}</span>
            @endforelse
        </div>
        @if($current)
            <a class="btn" href="{{ $team['url'] }}/archives/{{ $current->channel_id }}" target="_blank" rel="noopener noreferrer">Open #{{ $current->name }} in Slack ↗</a>
        @endif
    </div>

    @if($current)
        <div class="slack-pane" data-channel="{{ $current->channel_id }}">
            @if($error)
                <div class="callout warn-callout">{{ $error }}</div>
            @elseif(empty($messages))
                <div class="empty">No messages in #{{ $current->name }} yet.</div>
            @else
                <ol class="sl-list">
                    @foreach($messages as $m)
                        <li class="sl-msg">
                            @if($m['avatar'])<img src="{{ $m['avatar'] }}" alt="" width="36" height="36" class="sl-av" referrerpolicy="no-referrer">@else<span class="sl-av sl-av-blank" aria-hidden="true">{{ mb_substr($m['name'], 0, 1) }}</span>@endif
                            <div class="sl-body">
                                <div class="sl-head"><strong>{{ $m['name'] }}</strong> <a class="sl-time" href="{{ $m['link'] }}" target="_blank" rel="noopener noreferrer"><time datetime="{{ $m['iso'] }}">{{ $m['when'] }}</time></a></div>
                                <div class="sl-text">{!! $m['html'] !!}</div>
                                @if($m['files'])<div class="sl-files">Attachment: {{ implode(', ', $m['files']) }}</div>@endif
                                @if($m['replies'])<a class="sl-thread" href="{{ $m['link'] }}" target="_blank" rel="noopener noreferrer">{{ $m['replies'] }} {{ \Illuminate\Support\Str::plural('reply', $m['replies']) }} in Slack →</a>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <form class="sl-compose" data-slack-post="{{ route('slack.post', $current) }}">
                <label for="sl-text" class="sr">Message #{{ $current->name }}</label>
                <textarea id="sl-text" name="text" rows="2" maxlength="3000" required placeholder="Message #{{ $current->name }} (posts as {{ auth()->user()->name }} via OmniVEN)"></textarea>
                <div class="sl-compose-row">
                    <span class="sub sl-status" role="status">Enter to send · Shift+Enter for a new line</span>
                    <button class="btn primary" type="submit">Send</button>
                </div>
            </form>
        </div>
    @endif

    @can('admin')
        <details class="sl-admin">
            <summary>Manage channels (admins)</summary>
            <form method="POST" action="{{ route('slack.channels.store') }}" class="sl-add">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="#channel-name" aria-label="Slack channel name">
                <button class="btn" type="submit">Add channel</button>
            </form>
            <ul class="sl-chlist">
                @foreach($channels as $c)
                    <li>#{{ $c->name }}
                        <form method="POST" action="{{ route('slack.channels.destroy', $c) }}" data-confirm>@csrf @method('DELETE')
                            <button class="pi-link" type="submit" data-label="Remove">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <p class="sub">Public channels are joined automatically. For a private channel, type <code>/invite @OmniVEN</code> in it first.</p>
        </details>
    @endcan
@endif
