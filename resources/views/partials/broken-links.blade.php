{{-- Admins: results of the nightly link check. $return = where "Check now" comes back to. --}}
@php($lr = \App\Models\LinkCheck::report())
<div class="links-panel {{ $lr['broken']->isNotEmpty() ? 'has-broken' : '' }}" id="broken-links">
    <div class="h2row">
        <h2>Broken links @if($lr['broken']->isNotEmpty())<span class="links-n">{{ $lr['broken']->count() }}</span>@endif</h2>
        <span class="sub">
            @if($lr['last'])Checked {{ $lr['last']->timezone(config('app.timezone'))->format('M j, g:ia') }} · {{ $lr['total'] }} links{{ $lr['blocked'] ? ' · '.$lr['blocked'].' couldn’t be checked (the site blocks automated visits)' : '' }}@else Not checked yet. The check runs every night.@endif
        </span>
        <form method="POST" action="{{ route('links.check', ['return' => $return ?? 'access']) }}">@csrf<button class="btn" type="submit">Check now</button></form>
    </div>
    @if($lr['broken']->isEmpty())
        @if($lr['last'])<p class="sub links-ok">✓ Every link that could be checked is working.</p>@endif
    @else
        <ul class="links-list">
            @foreach($lr['broken'] as ['row' => $r, 'item' => $item])
                <li>
                    <div class="links-what">
                        <b>{{ \App\Support\BrandIcon::html(\App\Support\BrandIcon::forUrl($r->url), 'xs') }}{{ \App\Models\LinkCheck::itemName($item) }}</b>
                        <a class="links-url" href="{{ $r->url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $r->url), 70) }} ↗</a>
                    </div>
                    <span class="links-err">{{ $r->shortError() }} · since {{ $r->first_failed_at?->timezone(config('app.timezone'))->format('M j') }}</span>
                    @if($fix = \App\Models\LinkCheck::fixUrl($item))<a class="mini" href="{{ $fix }}">Fix</a>@endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
