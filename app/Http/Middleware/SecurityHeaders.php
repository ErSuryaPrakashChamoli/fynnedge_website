<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CSP is deliberately not stricter than this. Everything the site loads is
 * self-hosted through Vite (no external CDN scripts/styles/fonts anywhere in the
 * codebase — verified by grep, not assumed), so script-src/style-src/font-src can
 * all stay 'self'. Two exceptions are load-bearing, not oversights:
 *   - script-src needs 'unsafe-eval' because Alpine.js evaluates directive
 *     expressions (x-data, @click, etc.) via `new Function(...)`. Swapping to
 *     Alpine's separate CSP-safe build is a bigger, riskier change than what was
 *     asked for here.
 *   - style-src needs 'unsafe-inline' because Livewire injects an inline <style>
 *     block on every page, and a couple of progress-bar views set inline
 *     `style="width: ...%"` directly.
 * img-src allows https: in addition to self/data: so admin-authored rich-text
 * body content (Page/LoanProduct) can still reference an external image.
 * Verified via curl that the header is present and every route that was passing
 * before still returns 200 — that confirms the policy doesn't break page
 * rendering server-side. It does NOT confirm client-side JS behavior under the
 * policy, since this environment can't drive a real browser; if something in
 * Alpine/Livewire silently breaks, it will only show up as a browser console
 * error, not a failed request.
 *
 * apply() is also called from bootstrap/app.php's exception `respond()` hook —
 * a response built by Laravel's exception handler (e.g. the redirect an
 * unauthenticated admin route request becomes via AuthenticationException)
 * never passes back out through this middleware's `$next()` return, since the
 * exception unwinds outside the middleware pipeline entirely. Without that
 * second call site, every such redirect would silently ship with none of these
 * headers — found live while verifying this via curl, not theoretical.
 */
class SecurityHeaders
{
    private const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: https:; "
        ."font-src 'self'; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'none'";

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request));
    }

    public static function apply(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $response->headers->set('Content-Security-Policy', self::CSP);

        return $response;
    }
}
