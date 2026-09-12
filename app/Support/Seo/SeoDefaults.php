<?php

namespace App\Support\Seo;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The sitewide fallbacks behind every page's meta, Open Graph and Twitter/X
 * tags, from Admin → Website Settings → SEO & Tracking.
 *
 * The resolution order is the same for every tag and is applied here, once,
 * rather than repeated as `??` chains in the layout:
 *
 *   this page's own value  →  the sitewide default  →  a sensible derivation
 *
 * "A sensible derivation" is what keeps the tags complete on a site where an
 * admin has filled in nothing: the OG title falls back to the page title, the
 * Twitter description to the OG description, the Twitter image to the OG image.
 * That is deliberately not the same as duplicating the values in the database —
 * a blank field means "follow the page", not "empty tag".
 */
class SeoDefaults
{
    public const DEFAULT_DESCRIPTION = 'FynnEdge helps you find the right lender for your personal loan, home loan, business loan or loan against property — with clear, upfront eligibility.';

    public const TWITTER_CARDS = [
        'summary_large_image' => 'Summary with large image',
        'summary' => 'Summary (small square image)',
    ];

    public static function metaDescription(): string
    {
        return self::string('seo_meta_description') ?? self::DEFAULT_DESCRIPTION;
    }

    /**
     * An optional suffix-free title used when a view renders the layout with no
     * title of its own. Blank means "use the site name", which is what the
     * layout did before this was configurable.
     */
    public static function metaTitle(?string $siteName = null): ?string
    {
        return self::string('seo_meta_title') ?? $siteName;
    }

    /**
     * $pageOwn is the value THIS page set for itself (null on a page that
     * renders no title of its own); $fallback is what the page ends up
     * displaying once the sitewide meta defaults have been applied.
     *
     * Order matters and is easy to get backwards: a sitewide default must not
     * override a page that has its own title — it only fills the gap left by a
     * page that doesn't.
     */
    public static function ogTitle(?string $pageOwn, string $fallback): string
    {
        return $pageOwn ?: (self::string('seo_default_og_title') ?? $fallback);
    }

    public static function ogDescription(?string $pageOwn, ?string $fallback): ?string
    {
        return $pageOwn ?: (self::string('seo_default_og_description') ?? $fallback);
    }

    public static function twitterCard(?string $imageUrl): string
    {
        $card = self::string('seo_twitter_card');

        if ($card && array_key_exists($card, self::TWITTER_CARDS)) {
            return $card;
        }

        /*
         * Twitter renders summary_large_image as a bare link when there is no
         * image, so the card type has to follow the image, not the other way
         * round, whenever an admin hasn't pinned one.
         */
        return $imageUrl ? 'summary_large_image' : 'summary';
    }

    public static function twitterImageUrl(?string $pageTwitterImage, ?string $ogImageUrl): ?string
    {
        return $pageTwitterImage
            ?: self::fileUrl('seo_default_twitter_image')
            ?: $ogImageUrl;
    }

    /**
     * Rebuilds a canonical URL onto the admin-configured public base URL.
     *
     * Only used when `seo_canonical_base_url` is set: it exists for sites served
     * behind a proxy or on a second hostname, where url()->current() would
     * otherwise canonicalise pages to the internal host. A page's own canonical
     * (SeoMeta) always wins over both.
     */
    public static function canonical(?string $pageCanonical, string $currentUrl): string
    {
        if (filled($pageCanonical)) {
            return $pageCanonical;
        }

        $base = self::string('seo_canonical_base_url');

        if (! $base || ! filter_var($base, FILTER_VALIDATE_URL)) {
            return $currentUrl;
        }

        $path = (string) parse_url($currentUrl, PHP_URL_PATH);
        $query = parse_url($currentUrl, PHP_URL_QUERY);

        return rtrim($base, '/').($path ?: '/').($query ? '?'.$query : '');
    }

    /**
     * Absolutises a URL for the tags that are read off-site.
     *
     * Uploaded images resolve to a root-relative path (/storage/...) so the
     * same database serves correct markup on localhost, staging and
     * production — see the `public` disk in config/filesystems.php. That is
     * right for every <img src> on the page and wrong for og:image,
     * twitter:image and JSON-LD, which are fetched by crawlers that have no
     * page to resolve a relative path against. This adds the scheme and host
     * the request actually arrived on, rather than a configured APP_URL that
     * can be stale; an already-absolute URL (an external CDN, say) is
     * returned untouched.
     */
    public static function absolute(?string $url): ?string
    {
        return $url ? url($url) : null;
    }

    private static function string(string $key): ?string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function fileUrl(string $key): ?string
    {
        $path = self::string($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
