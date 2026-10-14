<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('people.index', ['users' => User::orderByDesc('is_admin')->orderBy('name')->get()]);
    }

    /** Add someone before their first sign-in, so tasks can be assigned to them. */
    public function store(Request $request): RedirectResponse
    {
        $domain = config('omniven.allowed_domain');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'ends_with:@'.$domain, 'unique:users,email'],
            'name' => ['required', 'string', 'max:120'],
        ], ['email.ends_with' => "Only @{$domain} addresses can be added."]);

        $user = User::create([
            'email' => strtolower($data['email']),
            'name' => $data['name'],
            'is_admin' => $request->boolean('is_admin'),
            'is_active' => true,
        ]);

        Activity::log('person.added', "Added {$user->name} ({$user->email})".($user->is_admin ? ' as admin' : ''), $user);

        return back()->with('status', "Added {$user->name}. They can sign in with Google now.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data = array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $data);

        if ($user->is($request->user())) {
            return back()->with('error', 'You can\'t change your own access.');
        }
        if ($user->isConfiguredAdmin() && (($data['is_admin'] ?? true) === false || ($data['is_active'] ?? true) === false)) {
            return back()->with('error', "{$user->name} is a permanent admin set in the server settings (OMNIVEN_ADMIN_EMAILS).");
        }

        $user->update($data);
        $what = array_key_exists('is_admin', $data) ? ($data['is_admin'] ? 'made admin' : 'made staff') : ($data['is_active'] ? 'access restored' : 'access turned off');
        Activity::log('person.updated', "{$user->name}: {$what}", $user);

        return back()->with('status', "Updated {$user->name}.");
    }
}
