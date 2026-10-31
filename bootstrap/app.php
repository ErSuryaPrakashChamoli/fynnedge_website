<?php

use App\Http\Middleware\EnsureCustomerPortalAccess;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\RecordNotFoundRequests;
use App\Http\Middleware\SearchEngineIndexingHeader;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSiteLocale;
use App\Support\Localization\SiteLocale;
use App\Support\NotFound\NotFoundRequests;
use App\Support\Privacy\CookieConsent;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The Equifax, Experian and CIBIL internal APIs: session-authenticated, so they
        // run in the web group rather than the public, anonymous routes/api.php.
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/equifax.php'));
            Route::middleware('web')->group(base_path('routes/experian.php'));
            Route::middleware('web')->group(base_path('routes/cibil.php'));
            // The external API for our other systems (e.g. the LMS): stateless, API key.
            Route::middleware('api')->group(base_path('routes/external.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'equifax.json' => ForceJsonResponse::class,
            'experian.json' => ForceJsonResponse::class,
            'cibil.json' => ForceJsonResponse::class,
            'customer.portal' => EnsureCustomerPortalAccess::class,
        ]);

        // The only browser-facing `auth` routes are the customer portal's
        // (`auth:customer`); staff JSON APIs answer 401 (ForceJsonResponse) and
        // Filament redirects to its own login. A signed-out visitor is sent to
        // the customer login, which returns them to the page they asked for.
        $middleware->redirectGuestsTo(fn (): string => route('customer.login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->routeIs('customer.*') ? route('portal.dashboard') : '/');
        // Must run BEFORE `auth` (which Laravel otherwise sorts first): a signed-out
        // API caller then gets a 401 JSON answer instead of a login redirect.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ForceJsonResponse::class);

        $middleware->appendToGroup('web', SecurityHeaders::class);
        // The public JSON API gets the same nosniff/framing/CSP headers as every page.
        $middleware->appendToGroup('api', SecurityHeaders::class);

        // GLOBAL, not on the `web` group: group middleware only runs once a route has
        // matched, and the main thing a redirect has to cover is a URL that no longer
        // matches any route at all. Registered here it also beats a live route, so a
        // renamed page can keep serving its old URL.
        $middleware->prepend(HandleRedirects::class);

        // Outermost, so it reads the final status of every request: counts each
        // genuine 404 in Admin → Missing pages. Global for the same reason as
        // HandleRedirects: an unmatched URL never reaches the `web` group.
        $middleware->prepend(RecordNotFoundRequests::class);

        // The consent banner writes this cookie from JavaScript — the only place the
        // visitor's answer exists — so Laravel must not expect it to be encrypted.
        // It holds only the accepted category names, never personal data.
        // The language switch's cookie holds only "en" or "hi" and is checked
        // against SiteLocale::SUPPORTED on every read, so it needs no encryption.
        $middleware->encryptCookies(except: [CookieConsent::COOKIE, SiteLocale::COOKIE]);

        // The language switch posts a form (so crawlers never find a URL for it),
        // but it must never answer a page left open past its session with a 419.
        // Exempting it is safe: all it can do is set that "en"/"hi" cookie, and
        // its return path is same-site only (SiteLocale::safeReturnPath()).
        $middleware->preventRequestForgery(except: ['language/*']);

        // Every request a visitor makes runs in their chosen language: pages,
        // the Livewire updates of public components, the public JSON API the
        // forms call, and the 404 for a URL (or route-bound slug) that matches
        // nothing. GLOBAL for that last reason, like HandleRedirects above: group
        // middleware never runs for an unmatched URL. SiteLocale::appliesTo()
        // keeps /admin and the Livewire requests its pages send in English.
        $middleware->append(SetSiteLocale::class);

        // Public routes only. The admin panel builds its own middleware array
        // (AdminPanelProvider) and is deliberately excluded — /admin is kept out of
        // search by authentication, not by an editor-facing setting.
        $middleware->appendToGroup('web', SearchEngineIndexingHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A scanner probe or a stale asset URL gets Laravel's bare 404, not the
        // branded page (errors/404.blade.php), which builds the whole site
        // header and footer. Every other missing page gets the branded one.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson() && NotFoundRequests::isNoise($request)) {
                return response()->view('errors.bare-404', status: Response::HTTP_NOT_FOUND);
            }
        });

        // A response built by the exception handler (e.g. the redirect an
        // AuthenticationException becomes) never passes back through the
        // SecurityHeaders middleware's $next() return — see that class's
        // docblock for why. This is the only other place a response leaves
        // the app, so it's the only other place these headers need setting.
        $exceptions->respond(fn (Response $response) => SecurityHeaders::apply($response));
    })->create();
