<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The single normalisation rule shared by every feature that matches an
 * admin-typed URL against an incoming request path (redirects, per-page SEO).
 *
 * Both sides go through it — the stored value on write and the request path on
 * match — so "/Old-Page", "old-page/" and "/old-page?utm_source=x" resolve to
 * one row instead of only matching when an admin happens to type the exact
 * form the visitor's browser sends.
 */
class UrlPath
{
    /**
     * A leading slash, no query string, no trailing slash, lowercased host-less
     * path. "/" itself is preserved — a site legitimately has rules for its own
     * homepage, and trimming it to "" would make every request match.
     */
    public static function normalize(string $path): string
    {
        $path = trim($path);

        if (str_contains($path, '://')) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = strtok($path, '?') ?: '/';
        $path = '/'.trim($path, '/');

        return strtolower($path);
    }

    /**
     * Whether a GET for this path reaches a route. Matched against a fresh
     * request, so the real one is never bound to a route here. A route whose
     * model binding then finds no record still counts: this answers "is this
     * a URL shape the site serves", not "does this exact page exist", which
     * only a real request can tell (seo:audit makes those).
     */
    public static function isRoutable(string $path): bool
    {
        try {
            Route::getRoutes()->match(Request::create($path));

            return true;
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
    }
}
