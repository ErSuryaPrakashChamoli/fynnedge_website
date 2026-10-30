<?php

namespace App\Enums;

/**
 * A group of public content that is cached and invalidated together.
 *
 * Each section has its own version (App\Support\ContentCache\ContentCache), so
 * a change to one never empties another: editing a rate leaves the settings,
 * menus and redirects cached. The tables listed are what bumps the section.
 */
enum ContentCacheSection: string
{
    /** `settings` — branding, contact, theme, tracking and SEO defaults. */
    case Settings = 'settings';

    /** `loan_products` and `loan_landing_pages` — the header mega menu and the sitemap. */
    case LoanCatalog = 'loan-catalog';

    /** RateBook's rows: `lender_product_rates`, `lender_products`, `lenders`, `loan_products`. */
    case Rates = 'rates';

    /** `redirects` — the active map read by HandleRedirects on every request. */
    case Redirects = 'redirects';

    /** `page_seos` — the per-URL SEO override map read by the layout. */
    case PageSeo = 'page-seo';

    /**
     * The dependency map: which sections a change to a table invalidates.
     * Models read it through InvalidatesContentCache; writes that bypass model
     * events (DB::table() backfills, saveQuietly()) through
     * ContentCache::invalidateTable(). A table not listed here feeds no cached
     * content — it is queried on every request.
     *
     * @return list<self>
     */
    public static function forTable(string $table): array
    {
        return match ($table) {
            'settings' => [self::Settings],
            // A product's slug, category and status are part of RateBook's rows too.
            'loan_products' => [self::LoanCatalog, self::Rates],
            'loan_landing_pages' => [self::LoanCatalog],
            'lender_product_rates', 'lender_products', 'lenders' => [self::Rates],
            'redirects' => [self::Redirects],
            'page_seos' => [self::PageSeo],
            default => [],
        };
    }
}
