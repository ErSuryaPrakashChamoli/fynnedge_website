<?php

namespace App\Support\Loans;

use App\Enums\ContentCacheSection;
use App\Enums\LandingPageGroup;
use App\Enums\LoanCategory;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\ContentCache\ContentCache;
use App\Support\Localization\SiteLocale;

/**
 * Single source of truth for the header "Loans" mega menu — mirrors
 * App\Support\Calculators\CalculatorCatalog's shape/convention.
 *
 * A category only appears here once it has a real JourneyDefinition (see
 * JourneySeeder) — otherwise "Check Eligibility" would link into a dead end
 * (JourneyController::start() degrades to "Applications aren't open yet"
 * for a product with no journey). CATEGORY_ORDER also fixes the display
 * order, most-common-first, rather than alphabetical.
 */
class LoanMegaMenu
{
    private const CATEGORY_ORDER = [
        LoanCategory::PersonalLoan,
        LoanCategory::FlexiHybridTermLoan,
        LoanCategory::HomeLoan,
        LoanCategory::CarLoan,
        LoanCategory::LoanAgainstProperty,
        LoanCategory::BusinessLoan,
        LoanCategory::CreditCard,
        LoanCategory::GoldLoan,
        LoanCategory::TwoWheelerLoan,
        LoanCategory::TermLoan,
        LoanCategory::TractorLoan,
        LoanCategory::MudraLoan,
    ];

    /**
     * @return array<int, array{
     *     label: string,
     *     overviewRoute: string,
     *     overviewParams: array<string, string>,
     *     applyRoute: string,
     *     applyParams: array<string, string>,
     *     groups: array<string, array<int, array{label: string, title: string, route: string, params: array<string, string>}>>,
     * }>
     */
    public static function categories(): array
    {
        // Rendered into the header of every page, and building it (~400
        // landing pages, labels in the visitor's language) cost ~65ms a page.
        // Route names and parameters, not URLs, so it holds no host.
        return ContentCache::remember(
            'loan-mega-menu:'.SiteLocale::current(),
            [ContentCacheSection::LoanCatalog],
            fn (): array => self::build(),
            scheduled: [LoanProduct::class, LoanLandingPage::class],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function build(): array
    {
        $products = LoanProduct::query()
            ->published()
            ->whereIn('category', self::CATEGORY_ORDER)
            ->with(['landingPages' => fn ($query) => $query->published()->orderBy('sort_order')->select(['id', 'loan_product_id', 'group', 'title', 'title_hi', 'slug', 'city', 'city_hi'])])
            ->get()
            ->keyBy(fn (LoanProduct $product) => $product->category->value);

        return collect(self::CATEGORY_ORDER)
            ->map(function (LoanCategory $category) use ($products) {
                /** @var LoanProduct|null $product */
                $product = $products->get($category->value);

                if (! $product) {
                    return null;
                }

                return [
                    'label' => $category->getLabel(),
                    'overviewRoute' => 'loans.show',
                    'overviewParams' => ['loanProduct' => $product->slug],
                    'applyRoute' => 'loans.apply',
                    'applyParams' => ['loanProduct' => $product->slug],
                    'groups' => collect(LandingPageGroup::cases())
                        ->mapWithKeys(fn (LandingPageGroup $group) => [
                            $group->getLabel() => $product->landingPages
                                ->where('group', $group)
                                ->map(fn ($page) => [
                                    'label' => $page->shortLabel(),
                                    'title' => $page->title,
                                    'route' => 'loans.landing-pages.show',
                                    'params' => ['loanProduct' => $product->slug, 'landingPage' => $page->slug],
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->filter(fn (array $items) => $items !== [])
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
