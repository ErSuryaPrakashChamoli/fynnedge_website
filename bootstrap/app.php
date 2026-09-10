<?php

use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\SearchEngineIndexingHeader;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Privacy\CookieConsent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SecurityHeaders::class);

        // GLOBAL, not on the `web` group: group middleware only runs once a route has
        // matched, and the main thing a redirect has to cover is a URL that no longer
        // matches any route at all. Registered here it also beats a live route, so a
        // renamed page can keep serving its old URL.
        $middleware->prepend(HandleRedirects::class);

        // The consent banner writes this cookie from JavaScript — the only place the
        // visitor's answer exists — so Laravel must not expect it to be encrypted.
        // It holds only the accepted category names, never personal data.
        $middleware->encryptCookies(except: [CookieConsent::COOKIE]);

        // Public routes only. The admin panel builds its own middleware array
        // (AdminPanelProvider) and is deliberately excluded — /admin is kept out of
        // search by authentication, not by an editor-facing setting.
        $middleware->appendToGroup('web', SearchEngineIndexingHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A response built by the exception handler (e.g. the redirect an
        // AuthenticationException becomes) never passes back through the
        // SecurityHeaders middleware's $next() return — see that class's
        // docblock for why. This is the only other place a response leaves
        // the app, so it's the only other place these headers need setting.
        $exceptions->respond(fn (Response $response) => SecurityHeaders::apply($response));
    })->create();
