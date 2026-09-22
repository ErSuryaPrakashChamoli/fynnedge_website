<?php

namespace App\Support\Calculators;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Whether search engines may index /calculators and each calculator page, set
 * from Admin → Website Settings → Calculators Page (Setting key
 * `calculator_pages_indexing`).
 *
 * Two levels: a switch for the whole calculator section, and a list of
 * individual pages to hide. The list stores the pages that are HIDDEN rather
 * than the ones that are indexed, so a missing settings row — or a calculator
 * newly added to CalculatorCatalog — is indexable by default and nothing falls
 * out of search silently.
 *
 * A hidden page gets `noindex, follow` (it still passes crawlers on to the
 * pages it links to) and is left out of /sitemap.xml. This can only ever make
 * a page LESS indexable: the sitewide SearchEngineIndexing switch still wins.
 */
class CalculatorIndexing
{
    public const SETTING_KEY = 'calculator_pages_indexing';

    public const INDEX_PAGE = 'index';

    public const NOINDEX = 'noindex, follow';

    /**
     * Every calculator page an admin can hide, keyed as in pageKey().
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return collect(CalculatorCatalog::groups())
            ->flatten(1)
            ->mapWithKeys(fn (array $calculator): array => [
                self::pageKey($calculator['route'], $calculator['params']) => $calculator['label'],
            ])
            ->prepend('All calculators page (/calculators)', self::INDEX_PAGE)
            ->all();
    }

    /**
     * A calculator page's stable key: its path under /calculators, e.g.
     * `gst` or `emi/home-loan`.
     *
     * @param  array<string, string>  $params
     */
    public static function pageKey(string $route, array $params = []): string
    {
        if ($route === 'calculators.index') {
            return self::INDEX_PAGE;
        }

        return collect([Str::after($route, 'calculators.'), ...array_values($params)])->implode('/');
    }

    public static function sectionEnabled(): bool
    {
        return (bool) (self::saved()['enabled'] ?? true);
    }

    /**
     * @return array<int, string>
     */
    public static function hiddenPages(): array
    {
        $hidden = self::saved()['noindex'] ?? [];

        return is_array($hidden) ? array_values(array_filter($hidden, 'is_string')) : [];
    }

    public static function isIndexable(string $pageKey): bool
    {
        return self::sectionEnabled() && ! in_array($pageKey, self::hiddenPages(), true);
    }

    /**
     * The robots value a calculator view passes to the layout — null leaves
     * the page on the sitewide default.
     */
    public static function robotsFor(string $pageKey): ?string
    {
        return self::isIndexable($pageKey) ? null : self::NOINDEX;
    }

    /**
     * @return array{enabled: bool, noindex: array<int, string>}
     */
    public static function formState(): array
    {
        return ['enabled' => self::sectionEnabled(), 'noindex' => self::hiddenPages()];
    }

    /**
     * @param  array{enabled?: mixed, noindex?: mixed}  $state
     */
    public static function save(array $state): void
    {
        $noindex = is_array($state['noindex'] ?? null) ? $state['noindex'] : [];

        Setting::set(self::SETTING_KEY, [
            'enabled' => (bool) ($state['enabled'] ?? true),
            'noindex' => array_values(array_intersect($noindex, array_keys(self::pages()))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function saved(): array
    {
        $saved = Setting::get(self::SETTING_KEY);

        return is_array($saved) ? $saved : [];
    }
}
