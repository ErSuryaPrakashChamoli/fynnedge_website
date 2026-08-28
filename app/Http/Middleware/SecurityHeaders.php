<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers with no realistic chance of breaking the existing Alpine/Livewire frontend —
 * a Content-Security-Policy is deliberately not set here, since Alpine's expression
 * evaluation needs 'unsafe-eval' and getting a CSP wrong fails silently in the browser
 * with no way to verify it from this environment. That's a follow-up for whoever can
 * test it in a real browser, not a gap to paper over here.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        return $response;
    }
}
