<?php

namespace App\Http\Middleware;

use App\Console\Commands\SeoAuditCommand;
use App\Models\Redirect;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Applies the admin-managed redirects table to public GET requests, and sends
 * a trailing-slash or doubled-slash URL to its one canonical form.
 *
 * Registered as GLOBAL middleware rather than on the `web` group: group
 * middleware only runs once a route has matched, and the main thing a redirect
 * has to cover is a URL that no longer matches any route at all. Running
 * globally also means it beats a live route, so a renamed page can keep serving
 * its old slug. Only GET/HEAD are redirected: 301-ing a POST silently drops the
 * request body, which would break form submissions rather than move them.
 *
 * The admin panel is skipped outright — a redirect row for /admin/... would
 * lock admins out of the very screen they would need to delete it from.
 *
 * The query string is preserved on the way through, so campaign parameters
 * survive the hop, and the whole active set is looked up from one cached
 * array — no query per request.
 *
 * Every answer is ONE hop to the final URL. Trailing slashes used to be
 * stripped by public/.htaccess before Laravel saw the request, so an old
 * "/page/" took two hops (/page/ → /page → its redirect) and a deleted
 * "/page/" redirected to a 404 — both reported by Search Console as "Page with
 * redirect". Here the redirect row is matched first, whatever the slashes, and
 * a slash variant is only redirected when its clean path is a real route; any
 * other URL gets its 404 straight away.
 */
class HandleRedirects
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = $request->getPathInfo();

        if (! $request->is(self::panelPath(), self::panelPath().'/*') && ($redirect = Redirect::resolve($path))) {
            $destination = self::resolveDestination($redirect['destination'], $request);

            /*
             * A row whose destination normalises back to the URL just requested
             * would bounce the browser against its own redirect limit. The form
             * rejects that at save time and Redirect::resolve() refuses a loop;
             * this is the last line of defence, for rows that became
             * self-referential some other way (a renamed route, an APP_URL
             * change) — serve the page instead of looping.
             */
            if (Redirect::normalizePath($destination) !== Redirect::normalizePath($path)) {
                // seo:audit requests every row on each run; counting those would
                // make dead rows look like old URLs that still get traffic.
                if ($request->userAgent() !== SeoAuditCommand::USER_AGENT) {
                    self::recordHit($path);
                }

                return redirect()->away($destination, $redirect['status_code']);
            }
        }

        $canonicalPath = self::canonicalPath($path);

        if ($canonicalPath !== $path && self::isRoutable($canonicalPath)) {
            return redirect()->away(self::resolveDestination($canonicalPath, $request), 301);
        }

        return $next($request);
    }

    /**
     * The path with repeated slashes collapsed and the trailing slash dropped
     * ("/" itself stays "/"). The result always starts with exactly one slash,
     * so it can never become a protocol-relative "//other.example" Location.
     */
    private static function canonicalPath(string $path): string
    {
        $collapsed = (string) preg_replace('#/{2,}#', '/', $path);

        return $collapsed === '/' ? '/' : rtrim($collapsed, '/');
    }

    /**
     * Whether a GET for this path reaches a route. Matched against a fresh
     * request, so the real one is never bound to a route here. A route whose
     * model binding then finds no record still counts — that URL takes one
     * hop to its 404, the same as before the slash moved out of .htaccess.
     */
    private static function isRoutable(string $path): bool
    {
        try {
            Route::getRoutes()->match(Request::create($path));

            return true;
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
    }

    /**
     * Usage counters, so an admin can see which old URLs still get traffic and
     * which rows are dead weight. Written with the query builder rather than a
     * model save on purpose: it fires no model events, so it does not bust the
     * redirect cache this request just read from.
     */
    private static function recordHit(string $path): void
    {
        Redirect::query()
            ->where('source_path', Redirect::normalizePath($path))
            ->update(['hits' => DB::raw('hits + 1'), 'last_used_at' => now()]);
    }

    /**
     * The admin panel's URL prefix, read from the panel itself so it follows
     * AdminPanelProvider's ->path() rather than a second hardcoded copy. Falls
     * back to the configured default if the panel cannot be resolved this early
     * in the request — this middleware runs before routing.
     */
    private static function panelPath(): string
    {
        try {
            return trim(Filament::getPanel('admin')->getPath(), '/') ?: 'admin';
        } catch (\Throwable) {
            return 'admin';
        }
    }

    private static function resolveDestination(string $destination, Request $request): string
    {
        $target = str_contains($destination, '://') ? $destination : url($destination);
        $query = $request->getQueryString();

        if (! $query || str_contains($target, '?')) {
            return $target;
        }

        return $target.'?'.$query;
    }
}
