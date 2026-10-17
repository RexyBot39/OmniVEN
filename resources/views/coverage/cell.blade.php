{{-- One brand × platform cell: the account, a gap (with ways to fill it), or "not needed". --}}
@php
    $a = $row['accounts'][$key] ?? null;
    $skipped = isset($row['skip'][$key]);
    $b = $row['brand'];
@endphp
<td class="cov-c {{ $a ? 'has' : ($skipped ? 'skip' : 'gap') }}">
    @if($a)
        <a class="cov-ok {{ $a->hasDirectLink() ? '' : 'weak' }} {{ $a->needsVerify() ? 'stale' : '' }} {{ $broken->has('account:'.$a->id) ? 'broken' : '' }}" href="{{ $a->openUrl() }}" target="_blank" rel="noopener noreferrer" title="{{ $a->title() }}">✓</a>
    @elseif($row['source'])
        <span class="cov-none" title="Uses {{ $row['source']->name }}’s accounts">·</span>
    @elseif($skipped)
        <span class="cov-skip" title="Not needed for {{ $b->name }}">—</span>
        @can('admin')<form method="POST" action="{{ route('coverage.skip') }}" class="cov-f">@csrf<input type="hidden" name="brand_id" value="{{ $b->id }}"><input type="hidden" name="platform" value="{{ $key }}"><button class="cov-btn" type="submit" title="Count {{ $p['label'] }} for {{ $b->name }} again">Needed</button></form>@endcan
    @else
        <details class="cov-menu">
            <summary aria-label="Missing: {{ $b->name }} {{ $p['label'] }}">+</summary>
            <div class="cov-pop">
                <b>{{ $b->name }} · {{ $p['label'] }}</b>
                @can('admin')<a href="{{ route('accounts.create', ['brand' => $b->slug, 'platform' => $key, 'return' => 'brands']) }}">Add the account</a>@endcan
                <a href="{{ route('work.create', ['type' => 'task', 'title' => 'Set up '.$b->name.' on '.$p['label'], 'details' => "Create or find {$b->name}'s {$p['label']} account".($p['home'] ? " ({$p['home']})" : '').", give the team access, and add it to OmniVEN under Brands so it shows on the grid."]) }}">Make a task</a>
                @can('admin')<form method="POST" action="{{ route('coverage.skip') }}">@csrf<input type="hidden" name="brand_id" value="{{ $b->id }}"><input type="hidden" name="platform" value="{{ $key }}"><button type="submit">Not needed for {{ $b->name }}</button></form>@endcan
            </div>
        </details>
    @endif
</td>
