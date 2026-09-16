<?php

namespace App\Support\Seo;

use App\Support\UrlPath;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Every fixed public page URL on the site, read straight off the router.
 *
 * Used as the suggestion list on the Page SEO form so an admin picks a real URL
 * instead of guessing at one — the single most likely way to create a row that
 * silently never matches.
 *
 * Deliberately only the routes that take no parameters. The parameterised ones
 * (/loans/{slug}, /resources/{slug}) are record-backed pages that already have
 * their own SEO section in the panel, and listing them here would push admins
 * towards editing them in the wrong place. The field still accepts any path
 * typed by hand.
 */
class PublicPagePaths
{
    private const EXCLUDED_PREFIXES = [
        '/admin', '/livewire', '/storage', '/up', '/_', '/newsletter', '/journey', '/applications',
    ];

    private const EXCLUDED_PATHS = ['/robots.txt', '/sitemap.xml'];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => in_array('GET', $route->methods(), true))
            ->reject(fn (RoutingRoute $route): bool => str_contains($route->uri(), '{'))
            ->map(fn (RoutingRoute $route): string => UrlPath::normalize('/'.$route->uri()))
            ->reject(fn (string $path): bool => Str::startsWith($path, self::EXCLUDED_PREFIXES))
            ->reject(fn (string $path): bool => in_array($path, self::EXCLUDED_PATHS, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
