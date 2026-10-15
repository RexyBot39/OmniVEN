@extends('layouts.app', ['title' => 'Access review'])

@php($pct = $total ? round($done / $total * 100) : 100)

@section('content')
<main class="wrap review">
    <a href="{{ route('dashboard') }}#access" class="back">← Back to Access</a>
    <h1 class="form-h">Access review · {{ $quarter }}@include('partials.star', ['key' => 'page:13', 'label' => 'the Access review'])</h1>
    <p class="sub">Once a quarter, check every login and owner is still right. Overdue and never-verified items are at the top.</p>

    <div class="rv-progress" role="img" aria-label="{{ $done }} of {{ $total }} verified this quarter">
        <div class="rv-bar"><span style="width:{{ $pct }}%"></span></div>
        <b>{{ $done }} of {{ $total }}</b> verified this quarter
    </div>

    <div class="callout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <div>Anyone who has left? Turn off their OmniVEN access on <a href="{{ route('people.index') }}">People</a> and remove them in each tool. Never put passwords or 2FA codes here. When you're done, tick “Quarterly access review” on <a href="{{ route('dashboard') }}#routines">Routines</a>.</div>
    </div>

    @include('partials.broken-links', ['return' => 'review'])

    @foreach([['Tools', $tools, 'tool'], ['Brand accounts', $accounts, 'account']] as [$heading, $items, $type])
        <div class="h2row" style="margin-top:24px"><h2>{{ $heading }}</h2><span class="sub">{{ $items->count() }}</span></div>
        @if($items->isEmpty())
            <div class="empty">Nothing here yet.</div>
        @else
            <div class="tablewrap">
                <table>
                    <thead><tr><th>{{ $type === 'tool' ? 'Tool' : 'Account' }}</th><th>Login account</th><th>Owner</th><th>Last verified</th><th><span class="sr">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach($items as $i)
                            @php($name = $type === 'tool' ? $i->name : $i->title())
                            <tr>
                                <td>{{ $name }}@if($type === 'tool' && $i->category)<div class="sub">{{ $i->category }}</div>@endif</td>
                                <td>{!! $i->login ? e($i->login) : '<span class="missing warn-t">No login listed</span>' !!}</td>
                                <td>{!! $i->owner ? e($i->owner) : '<span class="missing">Add</span>' !!}</td>
                                <td class="{{ $i->needsVerify() ? 'warn-t' : 'ok-t' }}">{{ $i->verified_on?->format('M j, Y') ?? 'Never' }}</td>
                                <td><div class="rv-acts">
                                    <form method="POST" action="{{ $type === 'tool' ? route('tools.verify', [$i, 'return' => 'review']) : route('accounts.verify', [$i, 'return' => 'review']) }}">@csrf<button class="btn {{ $i->needsVerify() ? 'primary' : '' }}" type="submit">Verified ✓</button></form>
                                    <a class="mini" href="{{ $type === 'tool' ? route('tools.edit', [$i, 'return' => 'review']) : route('accounts.edit', [$i, 'return' => 'review']) }}">Fix</a>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach
</main>
@endsection
