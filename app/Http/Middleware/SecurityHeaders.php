<?php

namespace App\Http\Middleware;

use App\Support\Analytics\TrackingScripts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CSP is deliberately not stricter than this. Everything the site loads is
 * self-hosted through Vite (no external CDN scripts/styles/fonts anywhere in the
 * codebase — verified by grep, not assumed), so script-src/style-src/font-src can
 * all stay 'self' in production. Three exceptions are load-bearing, not oversights:
 *   - script-src needs 'unsafe-eval' because Alpine.js evaluates directive
 *     expressions (x-data, @click, etc.) via `new Function(...)`. Swapping to
 *     Alpine's separate CSP-safe build is a bigger, riskier change than what was
 *     asked for here.
 *   - script-src also needs 'unsafe-inline' because Filament's own panel views
 *     (e.g. the sidebar) inject inline <script> blocks — for example, to seed the
 *     collapsed-navigation-group state into localStorage before Alpine boots.
 *     Without it the browser silently drops those scripts (no console-visible
 *     network failure, just a swallowed Alpine expression error downstream),
 *     leaving Alpine's sidebar store null instead of an array and breaking
 *     every navigation-group toggle. Confirmed for real: the admin sidebar's
 *     Catalog/Content groups rendered empty and their collapse buttons did
 *     nothing until this was added, with "Executing inline script violates ...
 *     script-src" CSP warnings in the console.
 *   - style-src needs 'unsafe-inline' because Livewire injects an inline <style>
 *     block on every page, and a couple of progress-bar views set inline
 *     `style="width: ...%"` directly.
 * frame-src allows Google's map domains (https://www.google.com and
 * https://maps.google.com) because the contact page embeds an admin-configured
 * Google Maps iframe (Setting `contact_map_url`, set via Filament Settings).
 * default-src has no frame-src fallback exception, so without this the browser
 * blocks the iframe outright with a console-only CSP violation — no failed
 * network request, just a blank box where the map should render.
 * img-src allows https: in addition to self/data: so admin-authored rich-text
 * body content (Page/LoanProduct) can still reference an external image. It
 * also allows blob: — Filament's FileUpload field (FilePond under the hood)
 * renders the local file preview from a blob: URL before the file is even
 * uploaded; without blob: here, the browser refuses to read that preview and
 * the upload can hang. connect-src and worker-src also allow blob: for the
 * same feature — FilePond can read the selected file's size/hash off a Web
 * Worker constructed from a blob: script URL, which falls under worker-src
 * (not covered by img-src's blob: allowance), and Firefox has been observed
 * enforcing this gap more strictly than Chromium for the same upload widget —
 * a page that uploads fine in one browser can still hang in the other if only
 * img-src is widened.
 *
 * Analytics is the one part of this policy that is not static: Google
 * Analytics/Tag Manager and any custom tracking script an admin enables in
 * Admin → Website Settings → SEO & Analytics load from third-party origins that
 * a 'self' script-src blocks outright — and blocks silently, with no failed
 * request and no server-side error, just an empty analytics report weeks later.
 * TrackingScripts::cspSources() therefore contributes the origins the currently
 * enabled tags need (and only those: with every toggle off, the policy is
 * byte-for-byte what it was before). A tag that fetches from an origin its own
 * pasted markup never mentions still needs adding there by hand.
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
        $tracking = TrackingScripts::cspSources();

        $scriptSrc = self::sourceList(["'self'", "'unsafe-eval'", "'unsafe-inline'", $viteOrigins['http'], ...$tracking['script']]);
        $styleSrc = self::sourceList(["'self'", "'unsafe-inline'", $viteOrigins['http']]);
        $imgSrc = self::sourceList(["'self'", 'data:', 'blob:', 'https:', $viteOrigins['http'], ...$tracking['img']]);
        $connectSrc = self::sourceList(["'self'", 'blob:', $viteOrigins['http'], $viteOrigins['ws'], ...$tracking['connect']]);
        $frameSrc = self::sourceList(["'self'", 'https://www.google.com', 'https://maps.google.com', ...$tracking['frame']]);

        return "default-src 'self'; "
            ."script-src {$scriptSrc}; "
            ."style-src {$styleSrc}; "
            ."img-src {$imgSrc}; "
            ."font-src 'self'; "
            ."connect-src {$connectSrc}; "
            ."frame-src {$frameSrc}; "
            ."worker-src 'self' blob:; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'none'";
    }

    /**
     * @param  array<int, string>  $sources
     */
    private static function sourceList(array $sources): string
    {
        return implode(' ', array_unique(array_filter($sources, fn (string $source) => $source !== '')));
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
