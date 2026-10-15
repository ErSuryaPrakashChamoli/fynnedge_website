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
        if (SiteLocale::appliesTo($request)) {
            app()->setLocale(SiteLocale::fromRequest($request));
        }

        return $next($request);
    }
}
