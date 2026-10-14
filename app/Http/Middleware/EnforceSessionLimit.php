<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs everyone out a fixed number of hours after they signed in (default 16), active or not. */
class EnforceSessionLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $started = (int) $request->session()->get('signed_in_at', 0);
            if ($started === 0) {
                // Sessions from before this rule (or restored any other way) start the clock now.
                $request->session()->put('signed_in_at', $started = now()->timestamp);
            }

            if (now()->timestamp - $started >= config('omniven.session_hours') * 3600) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'For security, you were signed out after '.config('omniven.session_hours').' hours. Sign in again to continue.');
            }
        }

        return $next($request);
    }
}
