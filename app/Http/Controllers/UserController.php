<?php

namespace App\Http\Controllers;

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

        return back()->with('status', "Updated {$user->name}.");
    }
}
