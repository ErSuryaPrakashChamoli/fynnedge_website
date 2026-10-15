<?php

namespace App\Http\Controllers;

use App\Support\Localization\SiteLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * The header's language switch: remembers the choice and returns the
     * visitor to the page they were on, now in that language. A plain link
     * rather than a form, so it works without JavaScript and can never fail
     * on an expired CSRF token.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        return redirect()
            ->to(SiteLocale::safeReturnPath($request->query('return')))
            ->withCookie(cookie(SiteLocale::COOKIE, $locale, SiteLocale::COOKIE_MINUTES));
    }
}
