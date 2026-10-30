<?php

namespace App\Http\Controllers;

use App\Support\Localization\SiteLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * The header's language switch: remembers the choice and returns the
     * visitor to the page they were on, now in that language. The switch posts
     * `return` as a form field; the GET form of the same URL (`?return=`) still
     * works for links crawled or cached before the switch became a form.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        return redirect()
            ->to(SiteLocale::safeReturnPath($request->input('return')))
            ->withCookie(cookie(SiteLocale::COOKIE, $locale, SiteLocale::COOKIE_MINUTES));
    }
}
