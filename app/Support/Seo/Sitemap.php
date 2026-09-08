<?php

namespace App\Support\Seo;

use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Support\Calculators\CalculatorCatalog;
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
        return LoanProduct::query()
            ->published()
            ->get()
            ->map(fn (LoanProduct $product): array => [
                'loc' => route('loans.show', $product),
                'lastmod' => self::lastmod($product->updated_at),
            ]);
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
        return LoanLandingPage::query()
            ->published()
            ->whereHas('loanProduct', fn ($query) => $query->published())
            ->with('loanProduct')
            ->get()
            ->map(fn (LoanLandingPage $page): array => [
                'loc' => route('loans.landing-pages.show', [
                    'loanProduct' => $page->loanProduct,
                    'landingPage' => $page,
                ]),
                'lastmod' => self::lastmod($page->updated_at),
            ]);
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    private static function articles(): Collection
    {
        return Article::query()
            ->published()
            ->get()
            ->map(fn (Article $article): array => [
                'loc' => route('resources.show', $article),
                'lastmod' => self::lastmod($article->updated_at),
            ]);
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    private static function legalPages(): Collection
    {
        return Page::query()
            ->published()
            ->whereIn('slug', self::ROUTED_PAGE_SLUGS)
            ->get()
            ->map(fn (Page $page): array => [
                'loc' => route($page->slug),
                'lastmod' => self::lastmod($page->updated_at),
            ]);
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
