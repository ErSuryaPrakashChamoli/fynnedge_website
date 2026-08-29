<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CSP is deliberately not stricter than this. Everything the site loads is
 * self-hosted through Vite (no external CDN scripts/styles/fonts anywhere in the
 * codebase — verified by grep, not assumed), so script-src/style-src/font-src can
 * all stay 'self' in production. Two exceptions are load-bearing, not oversights:
 *   - script-src needs 'unsafe-eval' because Alpine.js evaluates directive
 *     expressions (x-data, @click, etc.) via `new Function(...)`. Swapping to
 *     Alpine's separate CSP-safe build is a bigger, riskier change than what was
 *     asked for here.
 *   - style-src needs 'unsafe-inline' because Livewire injects an inline <style>
 *     block on every page, and a couple of progress-bar views set inline
 *     `style="width: ...%"` directly.
 * img-src allows https: in addition to self/data: so admin-authored rich-text
 * body content (Page/LoanProduct) can still reference an external image.
 *
 * Local dev with `npm run dev` (or `composer run dev`) is a second, genuinely
 * different origin: Vite's dev server serves JS/CSS with live HMR from its own
 * host:port (public/hot holds that URL — e.g. http://127.0.0.1:5173), which is
 * cross-origin from whatever port `artisan serve` is on. A 'self'-only CSP
 * blocks that origin outright, and the failure is silent: no console-visible
 * CSP violation reachable from here, just a page that loads with zero styling
 * or interactivity, because the <link>/<script> tags 404'd against the CSP
 * rather than the network. That happened for real — confirmed via a screenshot
 * of the unstyled site — before this got fixed. viteDevServerOrigin() reads
 * public/hot (present only while `npm run dev` is running; absent for a real
 * `npm run build` production asset set) and widens script-src/style-src/
 * connect-src to that exact origin, plus its ws:// counterpart for the HMR
 * websocket. Production is unaffected: no public/hot file, no widening.
 */
class SecurityHeaders
{
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
        $response->headers->set('Content-Security-Policy', self::csp());

        return $response;
    }

    private static function csp(): string
    {
        $viteOrigins = self::viteDevServerOrigins();

        $scriptSrc = trim("'self' 'unsafe-eval' {$viteOrigins['http']}");
        $styleSrc = trim("'self' 'unsafe-inline' {$viteOrigins['http']}");
        $imgSrc = trim("'self' data: https: {$viteOrigins['http']}");
        $connectSrc = trim("'self' {$viteOrigins['http']} {$viteOrigins['ws']}");

        return "default-src 'self'; "
            ."script-src {$scriptSrc}; "
            ."style-src {$styleSrc}; "
            ."img-src {$imgSrc}; "
            ."font-src 'self'; "
            ."connect-src {$connectSrc}; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'none'";
    }

    /**
     * @return array{http: string, ws: string} empty strings when Vite's dev server isn't running
     */
    private static function viteDevServerOrigins(): array
    {
        $hotFile = public_path('hot');

        if (! file_exists($hotFile)) {
            return ['http' => '', 'ws' => ''];
        }

        return self::parseHotFileUrl(file_get_contents($hotFile) ?: '');
    }

    /**
     * Pure and separately testable — the caller decides where the URL string
     * came from, so a test can exercise this without touching the real
     * public/hot file, which the actual `npm run dev` process owns.
     *
     * @return array{http: string, ws: string}
     */
    public static function parseHotFileUrl(string $contents): array
    {
        $parts = parse_url(trim($contents));

        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return ['http' => '', 'ws' => ''];
        }

        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $wsScheme = $parts['scheme'] === 'https' ? 'wss' : 'ws';

        return [
            'http' => "{$parts['scheme']}://{$parts['host']}{$port}",
            'ws' => "{$wsScheme}://{$parts['host']}{$port}",
        ];
    }
}
