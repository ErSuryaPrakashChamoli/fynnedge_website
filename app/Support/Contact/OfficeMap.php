<?php

namespace App\Support\Contact;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Everything built from the admin-set `contact_map_url` Setting (Settings →
 * Contact channels), shared by the contact page's map and the footer's office
 * map card so both open the same location the same way.
 */
class OfficeMap
{
    /**
     * The only hosts SecurityHeaders' CSP frame-src lets the map iframe load from.
     */
    private const EMBED_HOSTS = ['www.google.com', 'maps.google.com'];

    /**
     * Whether the footer shows the office map card. On by default, so a site that
     * has a map URL shows it until an admin switches it off.
     */
    public static function footerEnabled(): bool
    {
        return (bool) Setting::get('footer_map_enabled', true);
    }

    /**
     * The saved map URL, or null when there is none or it can't be framed — a
     * value saved before the Settings page converted links (e.g. a maps.app.goo.gl
     * short link) would otherwise render Chrome's "This content is blocked" box.
     */
    public static function embedUrl(): ?string
    {
        $url = Setting::get('contact_map_url');

        return is_string($url) && self::isEmbeddable($url) ? $url : null;
    }

    /**
     * The footer's office map card, or null when it's switched off or there is
     * no map URL to embed.
     *
     * @return array{embedUrl: string, viewUrl: ?string, directionsUrl: ?string, address: ?string}|null
     */
    public static function forFooter(): ?array
    {
        $embedUrl = self::embedUrl();

        if (! self::footerEnabled() || $embedUrl === null) {
            return null;
        }

        $address = Setting::get('contact_address');

        return [
            'embedUrl' => $embedUrl,
            'viewUrl' => self::viewUrl($embedUrl),
            'directionsUrl' => self::directionsUrl($embedUrl, $address),
            'address' => filled($address) ? $address : null,
        ];
    }

    /**
     * Turns whatever an admin pastes into a URL Google lets other sites frame, or
     * null when it can't. Google's own pages (/maps/place/..., and the maps.app.goo.gl
     * short links that redirect there) send X-Frame-Options: SAMEORIGIN, so they are
     * rebuilt as a `maps?q=<place>&ll=<lat,lng>&output=embed` search, which still
     * shows the business's Google listing card. Pasted Share → Embed a map code is
     * reduced to its iframe src.
     */
    public static function embedUrlFrom(string $input): ?string
    {
        $url = trim($input);

        if (preg_match('/<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $url, $iframe)) {
            $url = html_entity_decode($iframe[1]);
        }

        if (self::isShortLink($url)) {
            $url = self::resolveShortLink($url);
        }

        $parts = parse_url((string) $url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $queryString = $parts['query'] ?? '';

        if (! preg_match('/^(www\.|maps\.)?google\.[a-z.]+$/', $host)) {
            return null;
        }

        if (str_starts_with($path, '/maps/embed')) {
            return 'https://www.google.com'.$path.($queryString !== '' ? '?'.$queryString : '');
        }

        parse_str($queryString, $query);

        $isMapsSearch = $path === '/maps' || (str_starts_with($host, 'maps.') && in_array($path, ['', '/'], true));

        if ($isMapsSearch && filled($query['q'] ?? null)) {
            return 'https://maps.google.com/maps?'.$queryString.(($query['output'] ?? null) === 'embed' ? '' : '&output=embed');
        }

        // The place pin's exact coordinates (!3d<lat>!4d<lng>) beat the viewport centre (@lat,lng).
        $coordinates = preg_match('/!3d(-?[\d.]+)!4d(-?[\d.]+)/', $path, $match) || preg_match('#/@(-?[\d.]+),(-?[\d.]+)#', $path, $match)
            ? $match[1].','.$match[2]
            : null;

        if (preg_match('#^/maps/(?:place|search)/([^/@]+)#', $path, $place)) {
            return 'https://maps.google.com/maps?'.http_build_query(array_filter([
                'q' => urldecode($place[1]),
                'll' => $coordinates,
                'z' => 17,
                'output' => 'embed',
            ]));
        }

        if ($coordinates !== null) {
            return 'https://maps.google.com/maps?'.http_build_query(['q' => $coordinates, 'z' => 17, 'output' => 'embed']);
        }

        return null;
    }

    /**
     * Whether a URL is one of Google's frameable map variants on a host the CSP
     * allows: the /maps/embed?pb=... src from Share → Embed a map, or maps?...&output=embed.
     */
    public static function isEmbeddable(string $url): bool
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        return ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, self::EMBED_HOSTS, true)
            && (str_starts_with($parts['path'] ?? '', '/maps/embed') || ($query['output'] ?? null) === 'embed');
    }

    /**
     * A bare coordinate pin (`maps?q=<lat>,<lng>`) has no Google listing: clicking it in
     * the embed fails with "Place info couldn't load", and nothing names it. The map
     * component only then adds its own label and a click-catcher pointing here, to
     * open the same location on the full Google Maps site. A place search or a
     * /maps/embed?pb=... map shows Google's own listing card and named pin, so it
     * gets null and the embed is left to work untouched.
     */
    public static function viewUrl(?string $embedUrl): ?string
    {
        parse_str((string) parse_url((string) $embedUrl, PHP_URL_QUERY), $query);

        if (! is_string($query['q'] ?? null) || ! preg_match('/^-?[\d.]+,\s*-?[\d.]+$/', $query['q'])) {
            return null;
        }

        $viewUrl = preg_replace('/([?&])output=embed&?/', '$1', (string) $embedUrl);

        return rtrim($viewUrl, '?&');
    }

    /**
     * A Google Maps directions link to the office: the embed's own `q` location when
     * it has one (the pin's coordinates or the place name), otherwise the contact
     * address, since the `/maps/embed?pb=...` variant carries no plain location to read.
     */
    public static function directionsUrl(?string $embedUrl, ?string $address): ?string
    {
        parse_str((string) parse_url((string) $embedUrl, PHP_URL_QUERY), $query);

        $destination = is_string($query['q'] ?? null) && filled($query['q']) ? $query['q'] : $address;

        if (blank($destination)) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination);
    }

    private static function isShortLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'maps.app.goo.gl'
            || ($host === 'goo.gl' && str_starts_with((string) parse_url($url, PHP_URL_PATH), '/maps'));
    }

    /**
     * Short links only say where they lead in their redirect, so ask Google once, at save time.
     */
    private static function resolveShortLink(string $url): ?string
    {
        try {
            $location = Http::withoutRedirecting()->timeout(5)->get($url)->header('Location');
        } catch (ConnectionException) {
            return null;
        }

        return filled($location) ? $location : null;
    }
}
