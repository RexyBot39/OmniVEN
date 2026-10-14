<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // "hd" asks Google to show only Workspace accounts on our domain. It is a hint,
        // so the callback below re-checks the domain on the verified token data.
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with(['hd' => config('omniven.allowed_domain'), 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['error' => $e->getMessage()]);

            return $this->fail('Google sign-in didn\'t complete. Please try again.');
        }

        $domain = config('omniven.allowed_domain');
        $email = strtolower((string) $google->getEmail());
        $raw = $google->getRaw();
        $verified = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hostedDomain = strtolower((string) ($raw['hd'] ?? ''));

        if (! $verified || ! str_ends_with($email, '@'.$domain) || $hostedDomain !== $domain) {
            Log::notice('Rejected sign-in from outside domain', ['email' => $email, 'hd' => $hostedDomain]);

            return $this->fail("OmniVEN is only for @{$domain} accounts. Sign in with your work Google account.");
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first()
            ?? new User(['email' => $email]);

        if ($user->exists && ! $user->is_active) {
            return $this->fail('Your OmniVEN access has been turned off. Ask an admin to turn it back on.');
        }

        $user->fill([
            'name' => $google->getName() ?: $email,
            'email' => $email,
            'google_id' => $google->getId(),
            'avatar' => $google->getAvatar(),
            'last_login_at' => now(),
        ]);
        if ($user->isConfiguredAdmin()) {
            $user->is_admin = true;
        }
        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You\'re signed out.');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
