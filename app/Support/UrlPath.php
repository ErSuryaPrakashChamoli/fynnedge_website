<?php

namespace App\Support;

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
}
