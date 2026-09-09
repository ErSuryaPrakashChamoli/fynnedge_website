<?php

namespace App\Support\Faqs;

use App\Models\Faq;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Resolves the FAQs shown on the page currently being rendered.
 *
 * Two sources feed one list:
 *   - FAQs an admin pinned to this page via `faqs.placements` (route name)
 *   - FAQs the page already owns (a LoanProduct's, a Page's, the general set)
 *
 * They are merged rather than rendered as two separate blocks, because two
 * accordions would mean two FAQPage JSON-LD blocks on one URL — which Search
 * Console flags as duplicate structured data. One page, one FAQ section, one
 * FAQPage entity.
 */
class PageFaqs
{
    /**
     * @return Collection<int, Faq>
     */
    public static function forRoute(?string $routeName): Collection
    {
        if (blank($routeName)) {
            return new Collection;
        }

        return Faq::query()
            ->published()
            ->forPlacement($routeName)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Faq>
     */
    public static function forCurrentRoute(): Collection
    {
        return self::forRoute(Route::currentRouteName());
    }

    /**
     * The page's own FAQs first, then anything pinned to the page that isn't
     * already in that list. Keeping the owned FAQs ahead of the pinned ones
     * means a product's specific answers stay above the site-wide ones, and
     * de-duplicating by id makes this safe to call even if the same record
     * arrives from both sources.
     *
     * @param  Collection<int, Faq>  $ownFaqs
     * @return Collection<int, Faq>
     */
    public static function merge(Collection $ownFaqs, ?string $routeName = null): Collection
    {
        $routeName ??= Route::currentRouteName();

        return $ownFaqs
            ->concat(self::forRoute($routeName))
            ->unique(fn (Faq $faq): int => $faq->getKey())
            ->values();
    }
}
