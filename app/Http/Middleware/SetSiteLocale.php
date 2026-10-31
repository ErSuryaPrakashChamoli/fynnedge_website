<?php

namespace App\Http\Middleware;

use App\Support\Localization\SiteLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs every public page, and every Livewire update a public component makes,
 * in the language the visitor picked with the header switch.
 */
class SetSiteLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        self::apply($request);

        return $next($request);
    }

    /**
     * For a response built before this middleware runs, such as the 410 page
     * HandleRedirects answers for a Gone row.
     */
    public static function apply(Request $request): void
    {
        if (SiteLocale::appliesTo($request)) {
            app()->setLocale(SiteLocale::fromRequest($request));
        }
    }
}
