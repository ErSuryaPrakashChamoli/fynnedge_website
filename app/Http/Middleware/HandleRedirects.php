<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin-managed redirects table to public GET requests.
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

        if ($request->is(self::panelPath(), self::panelPath().'/*')) {
            return $next($request);
        }

        $redirect = Redirect::lookup($request->getPathInfo());

        if (! $redirect) {
            return $next($request);
        }

        $destination = self::resolveDestination($redirect['destination'], $request);

        /*
         * A row whose destination normalises back to the URL just requested
         * would bounce the browser against its own redirect limit. The form
         * rejects that at save time; this is the second line of defence, for
         * rows that became self-referential some other way (a renamed route,
         * an APP_URL change) — serve the page instead of looping.
         */
        if (Redirect::normalizePath($destination) === Redirect::normalizePath($request->getPathInfo())) {
            return $next($request);
        }

        self::recordHit($request->getPathInfo());

        return redirect()->away($destination, $redirect['status_code']);
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
