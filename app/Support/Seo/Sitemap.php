<?php

namespace App\Support\Seo;

use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\PageSeo;
use App\Models\SeoMeta;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Calculators\CalculatorCatalog;
use App\Support\Calculators\CalculatorIndexing;
use App\Support\Pages\CreditScoreIndexing;
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
 * per-visitor journey/application funnel (already `noindex, nofollow` in the
 * page head), signed draft previews and the health probe. The credit score
 * pages are funnel pages too and stay out unless an admin opts one in.
 *
 * Two rules apply to every entry, applied centrally in resolve() rather than
 * per source:
 *   - a URL whose effective robots value is `noindex` is EXCLUDED. Listing a
 *     URL that then tells the crawler not to index it is a self-contradiction,
 *     and it is the single most common way a sitemap and a page's meta drift
 *     apart.
 *   - a URL with a canonical is listed AT that canonical, since that is the URL
 *     it is asking to have indexed.
 *
 * "Effective" follows the layout's own precedence exactly: an active Page SEO
 * row for the URL (Content → Page SEOs) wins field by field, and a blank field
 * falls through to the value the page itself set (its record's SEO section, or
 * the calculator or credit score indexing setting).
 *
 * @phpstan-type SitemapEntry array{loc: string, lastmod: string|null}
 * @phpstan-type SitemapCandidate array{url: string, robots: string|null, canonical: string|null, lastmod: string|null}
 */
class Sitemap
{
    /**
     * CMS pages served by PageController, one route each (routes/web.php).
     */
    public const ROUTED_PAGE_SLUGS = ['grievance', 'privacy-policy', 'terms', 'disclaimer', 'credit-report-terms'];

    /**
     * CMS pages served by their OWN controllers (AboutController,
     * CareerController) at a route named after the slug. They 404 unless their
     * `pages` row is published, so they are listed from that row like every
     * other page — never as unconditional static entries.
     */
    public const CONTROLLER_PAGE_SLUGS = ['about', 'careers'];

    /**
     * Every `pages` slug a public URL depends on — renaming one 404s that URL.
     *
     * @return array<int, string>
     */
    public static function publicPageSlugs(): array
    {
        return [...self::ROUTED_PAGE_SLUGS, ...self::CONTROLLER_PAGE_SLUGS];
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    public static function entries(): Collection
    {
        return self::resolve(
            collect()
                ->concat(self::staticPages())
                ->concat(self::calculators())
                ->concat(self::creditScorePages())
                ->concat(self::loanProducts())
                ->concat(self::loanLandingPages())
                ->concat(self::articles())
                ->concat(self::cmsPages()),
        );
    }

    /**
     * Applies Page SEO overrides, then drops noindex URLs and swaps in
     * canonicals — the one place both rules live, for every source.
     *
     * Mirrors resources/views/components/layouts/app.blade.php: an active
     * PageSeo row's robots/canonical win when filled, otherwise the page's own
     * value stands. The rows are loaded in ONE query from the same cached
     * path map the layout reads, so a URL with no row costs nothing.
     *
     * @param  Collection<int, SitemapCandidate>  $candidates
     * @return Collection<int, SitemapEntry>
     */
    private static function resolve(Collection $candidates): Collection
    {
        $pageSeoIds = PageSeo::activeMap();

        $overrides = $pageSeoIds === []
            ? collect()
            : PageSeo::query()->with('seoMeta')->whereKey(array_values($pageSeoIds))->get()->keyBy('id');

        return $candidates
            ->map(function (array $candidate) use ($pageSeoIds, $overrides): array {
                $pageSeoId = $pageSeoIds[PageSeo::normalizePath($candidate['url'])] ?? null;
                $override = $pageSeoId !== null ? $overrides->get($pageSeoId) : null;

                return [
                    'robots' => $override?->seoRobots() ?: $candidate['robots'],
                    'loc' => $override?->seoCanonicalUrl() ?: ($candidate['canonical'] ?: $candidate['url']),
                    'lastmod' => $candidate['lastmod'],
                ];
            })
            ->reject(fn (array $entry): bool => str_contains(strtolower((string) $entry['robots']), 'noindex'))
            ->map(fn (array $entry): array => ['loc' => $entry['loc'], 'lastmod' => $entry['lastmod']])
            ->unique('loc')
            ->values();
    }

    /**
     * Turns published Seoable records into candidates carrying each record's
     * own robots and canonical, for resolve() to apply.
     *
     * The seo_metas rows are fetched in ONE query keyed by (type, id) rather
     * than lazily per record: eager-loading the morphOne would work too, but
     * every caller here already has its own `with()` needs, and the sitemap is
     * built from six sources — this keeps it at one extra query total.
     *
     * @param  Collection<int, Model>  $records
     * @param  callable(Model): string  $url
     * @return Collection<int, SitemapCandidate>
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
            ->map(fn ($record): array => [
                'url' => $url($record),
                'robots' => $meta->get($record->getKey())?->robots ?: null,
                'canonical' => $meta->get($record->getKey())?->canonical_url ?: null,
                'lastmod' => self::lastmod($record->updated_at),
            ])
            ->values();
    }

    /**
     * Pages with no record of their own. Only a Page SEO row can noindex or
     * re-canonicalise them, which resolve() applies.
     *
     * @return Collection<int, SitemapCandidate>
     */
    private static function staticPages(): Collection
    {
        return collect([
            'home',
            'loans.index',
            'eligibility.index',
            'quick-enquiry.show',
            'partners.index',
            'resources.index',
            'faqs.index',
            'contact',
        ])->map(fn (string $name): array => ['url' => route($name), 'robots' => null, 'canonical' => null, 'lastmod' => null]);
    }

    /**
     * Reuses the same catalog that renders the header mega menu and the
     * /calculators directory, so a calculator can't exist in one and not
     * the other. Pages hidden under Calculators Page → Search engine indexing
     * carry the same `robots` value their controller passes the layout, so
     * resolve() leaves them out.
     *
     * @return Collection<int, SitemapCandidate>
     */
    private static function calculators(): Collection
    {
        return collect(CalculatorCatalog::groups())
            ->flatten(1)
            ->prepend(['route' => 'calculators.index', 'params' => []])
            ->map(fn (array $calculator): array => [
                'url' => route($calculator['route'], $calculator['params']),
                'robots' => CalculatorIndexing::robotsFor(
                    CalculatorIndexing::pageKey($calculator['route'], $calculator['params']),
                ),
                'canonical' => null,
                'lastmod' => null,
            ]);
    }

    /**
     * Every bureau page, carrying the same `robots` value its controller
     * passes the layout, so resolve() drops all but the pages an admin opted
     * in under Credit Score Page → Search engines.
     *
     * @return Collection<int, SitemapCandidate>
     */
    private static function creditScorePages(): Collection
    {
        return collect(BureauName::cases())->map(fn (BureauName $bureau): array => [
            'url' => route('credit-score.show', ['bureau' => $bureau]),
            'robots' => CreditScoreIndexing::robotsFor($bureau),
            'canonical' => null,
            'lastmod' => null,
        ]);
    }

    /**
     * @return Collection<int, SitemapCandidate>
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
     * @return Collection<int, SitemapCandidate>
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
     * @return Collection<int, SitemapCandidate>
     */
    private static function articles(): Collection
    {
        return self::fromRecords(
            Article::query()->published()->get(),
            fn (Article $article): string => route('resources.show', $article),
        );
    }

    /**
     * Legal pages plus /about and /careers — every one has a route named after
     * its slug, and every one 404s unless its row is published.
     *
     * @return Collection<int, SitemapCandidate>
     */
    private static function cmsPages(): Collection
    {
        return self::fromRecords(
            Page::query()->published()->whereIn('slug', self::publicPageSlugs())->get(),
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
