@extends('layouts.app', ['title' => $account->exists ? 'Edit account' : 'Add account'])

@section('content')
<main class="wrap formpage">
    @php($back = $return === 'hub' && $account->brand ? route('brands.show', $account->brand) : route('dashboard').'#'.($return === 'access' ? 'access' : 'brands'))
    <a href="{{ $back }}" class="back">← Back</a>
    <h1 class="form-h">{{ $account->exists ? 'Edit '.$account->title() : 'Add an account' }}</h1>
    <p class="sub">One brand on one platform, for example Ocusafe on Instagram. Never enter passwords here.</p>
    @include('partials.form-errors')
    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}" class="card form">
        @csrf
        @if($account->exists) @method('PUT') @endif
        <input type="hidden" name="return" value="{{ $return }}">
        <div class="two">
            <div class="fld"><label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id">
                    <option value="">All brands / company-wide</option>
                    @foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('brand_id', $account->brand_id) == $b->id)>{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div class="fld"><label for="platform">Platform</label>
                <select id="platform" name="platform">
                    @foreach(config('omniven.platform_groups') as $gk => $gl)
                        <optgroup label="{{ $gl }}">
                            @foreach(config('omniven.platforms') as $pk => $p)
                                @if($p['group'] === $gk)<option value="{{ $pk }}" @selected(old('platform', $account->platform) === $pk)>{{ $p['label'] }}</option>@endif
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="two">
            <div class="fld"><label for="handle">Handle or account name</label><input id="handle" name="handle" type="text" maxlength="160" placeholder="@ocusafe" value="{{ old('handle', $account->handle) }}"></div>
            <div class="fld"><label for="account_ref">Account / property ID</label><input id="account_ref" name="account_ref" type="text" maxlength="120" placeholder="e.g. Merchant Center 1234567" value="{{ old('account_ref', $account->account_ref) }}"></div>
        </div>
        <div class="fld"><label for="url">Direct link</label><input id="url" name="url" type="url" maxlength="1024" placeholder="https://" value="{{ old('url', $account->url) }}">
            <span class="hint">The exact page for this brand's account (its profile, ad account or property), so one click lands in the right place.</span></div>
        <div class="two">
            <div class="fld"><label for="login">Login account</label><input id="login" name="login" type="text" maxlength="200" value="{{ old('login', $account->login) }}"><span class="hint">The email it's under. No passwords.</span></div>
            <div class="fld"><label for="owner">Owner / grants access</label><input id="owner" name="owner" type="text" maxlength="200" value="{{ old('owner', $account->owner) }}"></div>
        </div>
        <div class="fld"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes', $account->notes) }}</textarea></div>
        <div class="fld narrow"><label for="verified_on">Access last verified</label><input id="verified_on" name="verified_on" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ old('verified_on', $account->verified_on?->format('Y-m-d')) }}"></div>
        <div class="form-actions"><span class="spacer"></span><a class="btn" href="{{ $back }}">Cancel</a><button class="btn primary" type="submit">Save account</button></div>
    </form>
    @if($account->exists)
        @include('partials.delete', ['action' => route('accounts.destroy', [$account, 'return' => $return]), 'label' => 'Delete this account'])
    @endif
</main>
@endsection
