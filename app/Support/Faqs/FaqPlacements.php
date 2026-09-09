<?php

namespace App\Support\Faqs;

use App\Enums\FaqPlacement;
use App\Enums\LoanCategory;
use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\Calculators\LoanCalculatorPreset;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * Turns the route-level vocabulary in App\Enums\FaqPlacement into a per-page
 * one, so an admin can pin an FAQ to a single page — "Personal Loan" — rather
 * than only to every page of that kind.
 *
 * A placement token is either:
 *   `loans.show`                  every loan product page
 *   `loans.show:personal-loan`    that one product's page
 *
 * The generic form still matches every page of the type, so existing rows keep
 * working untouched and the two can be combined: one FAQ on every loan page,
 * another on just Home Loan. When a page is rendered it looks for BOTH of its
 * tokens, so a generic and a specific FAQ both surface.
 *
 * The identifier after the separator is the same slug the route already binds
 * on, never a database id — ids are meaningless to an admin reading the value
 * and would break if content were re-seeded.
 */
class FaqPlacements
{
    public const SEPARATOR = ':';

    /**
     * Routes serving many pages, mapped to the route parameter naming one.
     *
     * @var array<string, string>
     */
    private const PARAMETERISED = [
        'loans.show' => 'loanProduct',
        'loans.landing-pages.show' => 'landingPage',
        'resources.show' => 'article',
        'calculators.emi' => 'category',
        'calculators.eligibility' => 'category',
        'calculators.prepayment' => 'category',
    ];

    /**
     * Every placement token that applies to the page being rendered — the
     * route-wide one plus, on a parameterised route, the page-specific one.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, string>
     */
    public static function tokensFor(?string $routeName, array $parameters = []): array
    {
        if (blank($routeName)) {
            return [];
        }

        $tokens = [$routeName];
        $parameter = self::PARAMETERISED[$routeName] ?? null;

        if ($parameter !== null) {
            $identifier = self::identify($parameters[$parameter] ?? null);

            if (filled($identifier)) {
                $tokens[] = $routeName.self::SEPARATOR.$identifier;
            }
        }

        return $tokens;
    }

    /**
     * @return array<int, string>
     */
    public static function currentTokens(): array
    {
        $route = Route::current();

        return self::tokensFor($route?->getName(), $route?->parameters() ?? []);
    }

    /**
     * Route parameters arrive already resolved by implicit binding, so a model
     * has to be reduced back to the slug the route matched on.
     */
    private static function identify(mixed $parameter): ?string
    {
        if ($parameter instanceof Model) {
            return $parameter->getAttribute('slug');
        }

        if ($parameter instanceof BackedEnum) {
            return (string) $parameter->value;
        }

        return is_string($parameter) || is_int($parameter) ? (string) $parameter : null;
    }

    /**
     * Grouped options for the "Show on these pages" Select: every route-level
     * placement, with each parameterised route followed by its individual
     * pages. Only published records are offered — an admin pinning an FAQ to a
     * page nobody can reach would be silently doing nothing.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $options = [];

        foreach (FaqPlacement::cases() as $placement) {
            $options[$placement->group()][$placement->value] = $placement->getLabel();
        }

        $options['Loan product pages'] = self::expand(
            FaqPlacement::LoanProductPages,
            LoanProduct::query()->published()->orderBy('name')->pluck('name', 'slug')->all(),
        );

        $options['Loan landing pages'] = self::expand(
            FaqPlacement::LoanLandingPages,
            LoanLandingPage::query()->published()->orderBy('title')->pluck('title', 'slug')->all(),
        );

        $options['Article pages'] = self::expand(
            FaqPlacement::ArticlePages,
            Article::query()->published()->orderBy('title')->pluck('title', 'slug')->all(),
        );

        foreach ([
            FaqPlacement::EmiCalculators,
            FaqPlacement::EligibilityCalculators,
            FaqPlacement::PrepaymentCalculators,
        ] as $placement) {
            $options[$placement->getLabel()] = self::expand($placement, self::calculatorCategories());
        }

        // The per-page groups now carry the "every ..." entry themselves.
        foreach ([FaqPlacement::LoanProductPages, FaqPlacement::LoanLandingPages, FaqPlacement::ArticlePages] as $placement) {
            unset($options[$placement->group()][$placement->value]);
        }

        foreach ([FaqPlacement::EmiCalculators, FaqPlacement::EligibilityCalculators, FaqPlacement::PrepaymentCalculators] as $placement) {
            unset($options[$placement->group()][$placement->value]);
        }

        return array_filter($options, fn (array $group): bool => $group !== []);
    }

    /**
     * @param  array<string, string>  $records  slug => label
     * @return array<string, string>
     */
    private static function expand(FaqPlacement $placement, array $records): array
    {
        $group = [$placement->value => $placement->getLabel()];

        foreach ($records as $slug => $label) {
            $group[$placement->value.self::SEPARATOR.$slug] = $label;
        }

        return $group;
    }

    /**
     * Only categories with a real, published product behind them — the same
     * rule the calculator pages themselves apply.
     *
     * @return array<string, string>
     */
    private static function calculatorCategories(): array
    {
        return collect(LoanCalculatorPreset::supportedCategories())
            ->mapWithKeys(fn (LoanCategory $category): array => [$category->value => $category->getLabel()])
            ->all();
    }

    /**
     * Human label for a stored token, for the admin table's badges.
     */
    public static function label(string $token): string
    {
        if ($placement = FaqPlacement::tryFrom($token)) {
            return $placement->getLabel();
        }

        [$routeName, $identifier] = array_pad(explode(self::SEPARATOR, $token, 2), 2, null);

        if ($identifier === null) {
            return $token;
        }

        return match ($routeName) {
            'loans.show' => LoanProduct::query()->where('slug', $identifier)->value('name') ?? $identifier,
            'loans.landing-pages.show' => LoanLandingPage::query()->where('slug', $identifier)->value('title') ?? $identifier,
            'resources.show' => Article::query()->where('slug', $identifier)->value('title') ?? $identifier,
            'calculators.emi', 'calculators.eligibility', 'calculators.prepayment' => trim(
                (LoanCategory::tryFrom($identifier)?->getLabel() ?? $identifier).' — '.(FaqPlacement::tryFrom($routeName)?->getLabel() ?? $routeName)
            ),
            default => $token,
        };
    }
}
