@extends('layouts.app', ['title' => 'People'])

@section('content')
<main class="wrap formpage wide">
    <a href="{{ route('dashboard') }}" class="back">← Back to OmniVEN</a>
    <h1 class="form-h">People @include('partials.star', ['key' => 'page:12', 'label' => 'People'])</h1>
    <p class="sub">Anyone with an {{ '@'.config('omniven.allowed_domain') }} Google account can sign in and appears here after their first visit. Admins can edit tools, brands and routines; everyone can tick routines and post notes. Open a person to see their routines and tasks.</p>
    @include('partials.form-errors')
    <form method="POST" action="{{ route('people.store') }}" class="card add-person">
        @csrf
        <div class="fld grow"><label for="p-name">Name</label><input id="p-name" name="name" type="text" required maxlength="120" value="{{ old('name') }}"></div>
        <div class="fld grow"><label for="p-email">Work email</label><input id="p-email" name="email" type="text" inputmode="email" required placeholder="name@{{ config('omniven.allowed_domain') }}" value="{{ old('email') }}"></div>
        <label class="inline"><input type="checkbox" name="is_admin" value="1"> Admin</label>
        <button class="btn primary" type="submit">Add person</button>
    </form>
    <div class="tablewrap">
        <table>
            <thead><tr><th>Person</th><th>Last sign-in</th><th>Role</th><th>Access</th></tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td><a class="person-link" href="{{ route('people.show', $u) }}"><strong>{{ $u->name }}</strong></a><div class="sub">{{ $u->email }} · <a href="{{ route('people.show', $u) }}">Routines &amp; tasks</a></div></td>
                    <td>{{ $u->last_login_at?->format('M j, Y g:ia') ?? 'Not yet signed in' }}</td>
                    <td>
                        @if($u->is(auth()->user()) || $u->isConfiguredAdmin())
                            <span class="pill active">{{ $u->is_admin ? 'Admin' : 'Staff' }}</span>
                            @if($u->isConfiguredAdmin())<div class="sub">Set in server settings</div>@endif
                        @else
                            <form method="POST" action="{{ route('people.update', $u) }}">@csrf @method('PATCH')
                                <input type="hidden" name="is_admin" value="{{ $u->is_admin ? 0 : 1 }}">
                                <button class="btn" type="submit">{{ $u->is_admin ? 'Admin · Make staff' : 'Staff · Make admin' }}</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        @if($u->is(auth()->user()) || $u->isConfiguredAdmin())
                            <span class="sub">—</span>
                        @else
                            <form method="POST" action="{{ route('people.update', $u) }}">@csrf @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $u->is_active ? 0 : 1 }}">
                                <button class="btn {{ $u->is_active ? 'danger' : '' }}" type="submit">{{ $u->is_active ? 'Turn off access' : 'Restore access' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</main>
@endsection
