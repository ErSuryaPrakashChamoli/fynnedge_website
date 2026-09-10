<?php

namespace App\Support\Seo;

use App\Models\Setting;

/**
 * The sitewide "should search engines index this site at all" switch, set from
 * Admin → Website Settings → SEO & Analytics (Setting key `seo_indexing_enabled`).
 *
 * It is a kill switch, not a per-page control: when it is OFF every public
 * surface agrees — the layout's robots meta, the X-Robots-Tag header
 * (App\Http\Middleware\SearchEngineIndexingHeader), /robots.txt and
 * /sitemap.xml. Per-page `robots` values (SeoMeta, or the hardcoded
 * `noindex, nofollow` on the journey/application funnel) can only ever make a
 * page LESS indexable, never more, so this class resolves the two together in
 * metaRobots() rather than leaving each view to combine them.
 *
 * The default is deliberately "indexable": a production site whose settings row
 * is missing (fresh deploy, restored database, cleared table) must not silently
 * fall out of the index. Staging/dev flips it without touching the database via
 * SEO_INDEXING_ENABLED=false in .env — see config/seo.php.
 */
class SearchEngineIndexing
{
    public const NOINDEX = 'noindex, nofollow';

    public const DEFAULT_ROBOTS = 'index, follow';

    public static function enabled(): bool
    {
        return (bool) Setting::get('seo_indexing_enabled', config('seo.indexing_enabled', true));
    }

    /**
     * The final `<meta name="robots">` value for a page, given whatever that
     * page asked for. A sitewide OFF always wins.
     */
    public static function metaRobots(?string $pageRobots = null): string
    {
        if (! self::enabled()) {
            return self::NOINDEX;
        }

        return filled($pageRobots) ? $pageRobots : self::DEFAULT_ROBOTS;
    }
}
