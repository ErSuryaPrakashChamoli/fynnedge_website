<?php

namespace App\Support\Seo;

use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Support\Calculators\CalculatorCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The canonical, indexable public URL set.
 *
 * Everything here is resolved through named routes and the same
 * `published()` scopes the public controllers use, so an unpublished,
 * scheduled-for-later or expired record can never leak into the sitemap
 * while its page still 404s. Deliberately excluded: the admin panel, the
 * per-visitor journey/application/credit-score funnel (already
 * `noindex, nofollow` in the page head), signed draft previews and the
 * health probe.
 *
 * Two rules apply to every record-backed entry, applied centrally in
 * fromRecords() rather than per source:
 *   - a record whose SEO section sets a `noindex` robots value is EXCLUDED.
 *     Listing a URL that then tells the crawler not to index it is a
 *     self-contradiction, and it is the single most common way a sitemap and a
 *     page's meta drift apart.
 *   - a record with its own canonical URL is listed AT that canonical, since
 *     that is the URL it is asking to have indexed.
 *
 * @phpstan-type SitemapEntry array{loc: string, lastmod: string|null}
 */
class Sitemap
{
    /**
     * CMS pages that have a public route. `pages` also holds rows for /about
     * and /careers, which are served by their own controllers and listed as
     * static entries instead — including them here would emit each twice.
     */
    public const ROUTED_PAGE_SLUGS = ['grievance', 'privacy-policy', 'terms', 'disclaimer', 'credit-report-terms'];

    /**
     * @return Collection<int, SitemapEntry>
     */
    public static function entries(): Collection
    {
        return collect()
            ->concat(self::staticPages())
            ->concat(self::calculators())
            ->concat(self::loanProducts())
            ->concat(self::loanLandingPages())
            ->concat(self::articles())
            ->concat(self::legalPages())
            ->unique('loc')
            ->values();
    }

    /**
     * Turns published Seoable records into entries, dropping the ones marked
     * noindex and preferring each record's canonical URL over its route URL.
     *
     * The seo_metas rows are fetched in ONE query keyed by (type, id) rather
     * than lazily per record: eager-loading the morphOne would work too, but
     * every caller here already has its own `with()` needs, and the sitemap is
     * built from six sources — this keeps it at one extra query total.
     *
     * @param  Collection<int, Model>  $records
     * @param  callable(Model): string  $url
     * @return Collection<int, SitemapEntry>
     */
    private static function fromRecords(Collection $records, callable $url): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        $meta = SeoMeta::query()
            ->where('seoable_type', $records->first()->getMorphClass())
            ->whereIn('seoable_id', $records->modelKeys())
            ->get()
            ->keyBy('seoable_id');

        return $records
            ->reject(fn ($record): bool => str_contains(
                strtolower((string) $meta->get($record->getKey())?->robots),
                'noindex',
            ))
            ->map(fn ($record): array => [
                'loc' => $meta->get($record->getKey())?->canonical_url ?: $url($record),
                'lastmod' => self::lastmod($record->updated_at),
            ])
            ->values();
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    private static function staticPages(): Collection
    {
        return collect([
            'home',
            'loans.index',
            'eligibility.index',
            'calculators.index',
            'resources.index',
            'faqs.index',
            'about',
            'careers',
            'contact',
        ])->map(fn (string $name): array => ['loc' => route($name), 'lastmod' => null]);
    }

    /**
     * Reuses the same catalog that renders the header mega menu and the
     * /calculators directory, so a calculator can't exist in one and not
     * the other.
     *
     * @return Collection<int, SitemapEntry>
     */
    private static function calculators(): Collection
    {
        return collect(CalculatorCatalog::groups())
            ->flatten(1)
            ->map(fn (array $calculator): array => [
                'loc' => route($calculator['route'], $calculator['params']),
                'lastmod' => null,
            ]);
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    private static function loanProducts(): Collection
    {
        return self::fromRecords(
            LoanProduct::query()->published()->get(),
            fn (LoanProduct $product): string => route('loans.show', $product),
        );
    }

    /**
     * Scoped to landing pages whose parent product is itself published —
     * LoanLandingPageController 404s otherwise, and a sitemap entry for a
     * 404 is a crawl-budget error.
     *
     * @return Collection<int, SitemapEntry>
     */
    private static function loanLandingPages(): Collection
    {
        return self::fromRecords(
            LoanLandingPage::query()
                ->published()
                ->whereHas('loanProduct', fn ($query) => $query->published())
                ->with('loanProduct')
                ->get(),
            fn (LoanLandingPage $page): string => route('loans.landing-pages.show', [
                'loanProduct' => $page->loanProduct,
                'landingPage' => $page,
            ]),
        );
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    private static function articles(): Collection
    {
        return self::fromRecords(
            Article::query()->published()->get(),
            fn (Article $article): string => route('resources.show', $article),
        );
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    private static function legalPages(): Collection
    {
        return self::fromRecords(
            Page::query()->published()->whereIn('slug', self::ROUTED_PAGE_SLUGS)->get(),
            fn (Page $page): string => route($page->slug),
        );
    }

    /**
     * Only emitted when the app actually tracks a modification time — a
     * fabricated "today" on every URL teaches crawlers to ignore lastmod.
     */
    private static function lastmod(?Carbon $updatedAt): ?string
    {
        return $updatedAt?->toAtomString();
    }
}
