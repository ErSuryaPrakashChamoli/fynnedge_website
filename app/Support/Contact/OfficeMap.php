<?php

namespace App\Support\Contact;

use App\Models\Setting;

/**
 * Everything built from the admin-set `contact_map_url` Setting (Settings →
 * Contact channels), shared by the contact page's map and the footer's office
 * map card so both open the same location the same way.
 */
class OfficeMap
{
    /**
     * Whether the footer shows the office map card. On by default, so a site that
     * has a map URL shows it until an admin switches it off.
     */
    public static function footerEnabled(): bool
    {
        return (bool) Setting::get('footer_map_enabled', true);
    }

    /**
     * The footer's office map card, or null when it's switched off or there is
     * no map URL to embed.
     *
     * @return array{embedUrl: string, viewUrl: ?string, directionsUrl: ?string, address: ?string}|null
     */
    public static function forFooter(): ?array
    {
        $embedUrl = Setting::get('contact_map_url');

        if (! self::footerEnabled() || blank($embedUrl)) {
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
     * The embed variant of a `maps?q=...` link fails to load place details when clicked
     * on a bare coordinate pin with no registered Google Business listing — the map
     * component catches that click with an overlay pointing here instead, to open the same
     * location on the full Google Maps site rather than the broken embedded info window.
     * The dedicated `/maps/embed?pb=...` variant (from Share > Embed a map) has no plain
     * equivalent to link to, so it's left as null and the click passes through untouched.
     */
    public static function viewUrl(?string $embedUrl): ?string
    {
        if (! $embedUrl || str_contains($embedUrl, '/maps/embed')) {
            return null;
        }

        $viewUrl = preg_replace('/([?&])output=embed&?/', '$1', $embedUrl);

        return rtrim($viewUrl, '?&');
    }

    /**
     * A Google Maps directions link to the office: the embed's own `q` location when
     * it has one (the coordinates the pin sits on), otherwise the contact address,
     * since the `/maps/embed?pb=...` variant carries no plain location to read.
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
}
