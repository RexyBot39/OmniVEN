@extends('layouts.app', ['title' => $brand->name])

@php($isAdmin = auth()->user()->can('admin'))

@section('content')
<div class="hub-band" style="background:var(--c{{ $brand->colorSlot() }})"></div>
<main class="wrap hub">
    <a href="{{ route('dashboard') }}#brands" class="back">← All brands</a>

    <header class="hub-head">
        <div class="hub-id">
            <div class="hub-title"><h1 class="form-h">{{ $brand->name }}</h1>@include('partials.star', ['item' => $brand])</div>
            @if($brand->site)
                <a class="hub-site" href="{{ $brand->site }}" target="_blank" rel="noopener noreferrer">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($brand->site, '/')) }} ↗</a>
            @else
                <span class="missing">No website added</span>
            @endif
            @if($brand->focus)<p class="hub-focus">{{ $brand->focus }}</p>@endif
        </div>
        @if($isAdmin)
            <div class="hub-actions">
                <a class="btn" href="{{ route('brands.edit', $brand) }}">Edit brand</a>
                <a class="btn primary" href="{{ route('accounts.create', ['brand' => $brand->slug, 'return' => 'hub']) }}">+ Add account</a>
            </div>
        @endif
    </header>

    <section>
        <div class="h2row"><h2>Accounts</h2><span class="sub">Every {{ $brand->name }} account, one click each</span></div>
        @if($groups->isEmpty())
            <div class="empty">No accounts added for {{ $brand->name }} yet.{{ $isAdmin ? ' Use + Add account, or the gaps below.' : ' Ask an admin to add them.' }}</div>
        @else
            <div class="acct-groups">
                @foreach($groups as $g)
                    <div class="acct-group">
                        <div class="cat-h">{{ $g['label'] }}</div>
                        <div class="acct-list">
                            @foreach($g['accounts'] as $a)
                                <article class="acct-card">
                                    <span class="plat" data-p="{{ $a->platform }}">{{ $a->platformInfo()['short'] }}</span>
                                    <div class="acct-main">
                                        <div class="acct-t"><b>{{ $a->platformLabel() }}</b>@if($a->handle)<span class="acct-handle">{{ $a->handle }}</span>@endif</div>
                                        <div class="acct-meta">
                                            @if($a->account_ref)<span>ID {{ $a->account_ref }}</span>@endif
                                            @if($a->login)<span>Login {{ $a->login }}</span>@endif
                                            @if($a->owner)<span>Owner {{ $a->owner }}</span>@endif
                                            @if($a->verified_on)
                                                <span class="{{ $a->needsVerify() ? 'warn-t' : 'ok-t' }}">{{ $a->needsVerify() ? 'Last verified' : 'Verified' }} {{ $a->verified_on->format('M j, Y') }}</span>
                                            @else
                                                <span class="warn-t">Access not verified</span>
                                            @endif
                                        </div>
                                        @if($a->notes)<p class="acct-notes">{{ $a->notes }}</p>@endif
                                    </div>
                                    <div class="acct-actions">
                                        @include('partials.star', ['item' => $a])
                                        @include('partials.request-access', ['item' => $a, 'return' => 'hub:'.$brand->slug])
                                        @if($a->openUrl())
                                            <a class="btn {{ $a->hasDirectLink() ? 'primary' : '' }}" href="{{ $a->openUrl() }}" data-lp="{{ $a->launchKey() }}" target="_blank" rel="noopener noreferrer" title="{{ $a->hasDirectLink() ? 'Opens this account' : 'No direct link yet: opens the platform sign-in' }}">Open ↗</a>
                                        @endif
                                        @if($isAdmin)
                                            <span class="acct-admin">
                                            <a class="mini" href="{{ route('accounts.edit', [$a, 'return' => 'hub']) }}">Edit</a>
                                            <form method="POST" action="{{ route('accounts.verify', [$a, 'return' => 'hub']) }}">@csrf<button class="mini linkbtn" type="submit">Mark verified</button></form>
                                            </span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($missing->isNotEmpty())
            <div class="gaps">
                <span class="sub">Not set up yet:</span>
                @foreach($missing as $key => $p)
                    @if($isAdmin)
                        <a class="gap-chip" href="{{ route('accounts.create', ['brand' => $brand->slug, 'platform' => $key, 'return' => 'hub']) }}">+ {{ $p['label'] }}</a>
                    @else
                        <span class="gap-chip off">{{ $p['label'] }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <div class="hub-cols">
        <section>
            <div class="h2row"><h2>Tools for {{ $brand->name }}</h2><span class="sub">{{ $tools->count() }}</span></div>
            <ul class="hub-tools">
                @foreach($tools as $t)
                    <li>
                        @if($t->url)<a href="{{ $t->url }}" target="_blank" rel="noopener noreferrer" data-lp="{{ $t->launchKey() }}">{{ $t->name }} ↗</a>@else<span>{{ $t->name }}</span>@endif
                        <span class="sub">{{ $t->category }}{{ $t->all_brands ? '' : ' · just this brand' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
        <section>
            <div class="h2row"><h2>Routines</h2><span class="sub">{{ $routines->reject->isDone()->count() }} due</span></div>
            <ul class="hub-routines">
                @forelse($routines as $r)
                    <li class="{{ $r->isDone() ? 'done' : '' }}">
                        <span class="cad">{{ config('omniven.cadences')[$r->cadence] ?? '' }}</span>
                        <span class="rt">{{ $r->title }}</span>
                        @if($r->isDone())<span class="ok-t">✓ {{ $r->latestCompletion->user?->firstName() }}</span>@endif
                    </li>
                @empty
                    <li class="sub">No routines for this brand.</li>
                @endforelse
            </ul>
            <a class="mini" href="{{ route('dashboard') }}#routines">Tick routines on the Routines tab →</a>
        </section>
    </div>
</main>
@endsection
