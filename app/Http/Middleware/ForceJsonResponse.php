<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a session-authenticated JSON endpoint answer every caller in JSON —
 * including a logged-out one, who gets a 401 instead of a redirect to a login
 * page — whatever Accept header the caller sent. Used by routes/equifax.php.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
