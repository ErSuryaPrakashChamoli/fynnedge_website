<?php

namespace App\Support\PromoBars;

use App\Models\PromoBar;
use App\Support\Faqs\FaqPlacements;

/**
 * Picks the ONE promo bar for the page being rendered.
 *
 * Placement reuses the FAQ vocabulary (see FaqPlacements) plus EVERY_PAGE,
 * like video testimonials. Among the bars that qualify:
 *   - a bar whose "Hide on these pages" lists this page is skipped;
 *   - a bar whose button links to this very page is skipped;
 *   - a bar pinned to this page by name beats a site-wide one, so a Home Loan
 *     offer can replace the general offer on the Home Loan page;
 *   - otherwise the lowest sort order wins.
 */
class PromoBars
{
    public const EVERY_PAGE = 'all';

    public static function forCurrentPage(): ?PromoBar
    {
        $tokens = FaqPlacements::currentTokens();
        $request = request();

        return PromoBar::query()
            ->published()
            ->forPlacements([self::EVERY_PAGE, ...$tokens])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn (PromoBar $promoBar): bool => $promoBar->ctaUrl() === null
                || $promoBar->isExcludedFrom($tokens)
                || $promoBar->linksTo($request->getHost(), $request->path()))
            ->sortBy(fn (PromoBar $promoBar): int => $promoBar->isPinnedToAnyOf($tokens) ? 0 : 1)
            ->first();
    }

    /**
     * Grouped options for the "Show on these pages" Select.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        return [
            'Site-wide' => [self::EVERY_PAGE => 'Every page on the website'],
            ...FaqPlacements::options(),
        ];
    }

    public static function label(string $token): string
    {
        return $token === self::EVERY_PAGE ? 'Every page' : FaqPlacements::label($token);
    }
}
