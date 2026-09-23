<?php

namespace App\Support\Pages;

use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;

/**
 * Whether search engines may index each /credit-score/{bureau} page, set per
 * page from Admin → Website Settings → Credit Score Page (Setting key
 * `credit_score_pages_indexing`) — the credit score counterpart of
 * CalculatorIndexing.
 *
 * The one difference is the default. The check pages started out as part of
 * the per-visitor funnel, kept out of search entirely, so the list stores the
 * pages that are INDEXED: a missing settings row leaves every page exactly as
 * before (`noindex, nofollow`, disallowed in robots.txt, not in the sitemap),
 * and a page only becomes indexable when an admin opts it in. An opted-in page
 * is allowed in robots.txt, listed in /sitemap.xml and left on the sitewide
 * robots default. The sitewide SearchEngineIndexing switch still wins.
 *
 * Kept out of the page's wording Setting on purpose, so "Reset to defaults"
 * never changes which pages search engines see.
 */
class CreditScoreIndexing
{
    public const SETTING_KEY = 'credit_score_pages_indexing';

    public const NOINDEX = 'noindex, nofollow';

    /**
     * @return array<int, BureauName>
     */
    public static function indexedPages(): array
    {
        $saved = Setting::get(self::SETTING_KEY);
        $indexed = is_array($saved) && is_array($saved['indexed'] ?? null) ? $saved['indexed'] : [];

        return array_values(array_filter(array_map(
            fn (mixed $value): ?BureauName => is_string($value) ? BureauName::tryFrom($value) : null,
            $indexed,
        )));
    }

    public static function isIndexable(BureauName $bureau): bool
    {
        return in_array($bureau, self::indexedPages(), true);
    }

    /**
     * The robots value the credit score view passes to the layout — null
     * leaves the page on the sitewide default.
     */
    public static function robotsFor(BureauName $bureau): ?string
    {
        return self::isIndexable($bureau) ? null : self::NOINDEX;
    }

    public static function setIndexable(BureauName $bureau, bool $indexable): void
    {
        $indexed = collect(self::indexedPages())
            ->reject(fn (BureauName $page): bool => $page === $bureau)
            ->when($indexable, fn ($pages) => $pages->push($bureau))
            ->map(fn (BureauName $page): string => $page->value)
            ->values()
            ->all();

        Setting::set(self::SETTING_KEY, ['indexed' => $indexed]);
    }
}
