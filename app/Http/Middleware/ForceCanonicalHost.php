<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends visitors on any other hostname (e.g. the *.up.railway.app address) to APP_URL,
 * so sign-in always starts and finishes on the same domain. Google's callback only
 * works when the browser's session cookie belongs to the same host.
 */
class ForceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (app()->environment('production') && $canonical && ! $request->is('up')
            && strcasecmp($request->getHost(), $canonical) !== 0) {
            return redirect()->to(rtrim(config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
