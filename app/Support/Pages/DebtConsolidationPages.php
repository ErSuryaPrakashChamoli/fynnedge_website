<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;

/**
 * The seven public Debt Consolidation pages. Each is a `pages` row edited in
 * Website Pages → General Pages (title, intro, body, SEO, FAQs, draft,
 * schedule, preview, Hindi, history) and served at a fixed address by its
 * own named route, so nested addresses like /debt-consolidation/faqs work
 * and the slug is locked in the editor (Sitemap::publicPageSlugs()).
 *
 * Addresses follow the site's canonical form, without a trailing slash; the
 * slashed form answers with one 301 (HandleRedirects).
 */
final class DebtConsolidationPages
{
    /**
     * @var array<string, array{slug: string, route: string, path: string}>
     */
    public const PAGES = [
        'hub' => ['slug' => 'debt-consolidation', 'route' => 'debt-consolidation.hub', 'path' => '/debt-consolidation'],
        'personal' => ['slug' => 'personal-debt-consolidation', 'route' => 'debt-consolidation.personal', 'path' => '/personal-debt-consolidation'],
        'business' => ['slug' => 'business-debt-consolidation', 'route' => 'debt-consolidation.business', 'path' => '/business-debt-consolidation'],
        'how_it_works' => ['slug' => 'debt-consolidation-how-it-works', 'route' => 'debt-consolidation.how-it-works', 'path' => '/debt-consolidation/how-it-works'],
        'calculator' => ['slug' => 'debt-consolidation-calculator', 'route' => 'debt-consolidation.calculator', 'path' => '/debt-consolidation/calculator'],
        'assessment' => ['slug' => 'debt-consolidation-assessment', 'route' => 'debt-consolidation.assessment', 'path' => '/debt-consolidation/assessment'],
        'faqs' => ['slug' => 'debt-consolidation-faqs', 'route' => 'debt-consolidation.faqs', 'path' => '/debt-consolidation/faqs'],
    ];

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_values(array_column(self::PAGES, 'slug'));
    }

    public static function keyForSlug(string $slug): ?string
    {
        foreach (self::PAGES as $key => $page) {
            if ($page['slug'] === $slug) {
                return $key;
            }
        }

        return null;
    }

    public static function routeNameFor(string $slug): ?string
    {
        $key = self::keyForSlug($slug);

        return $key === null ? null : self::PAGES[$key]['route'];
    }

    public static function slug(string $key): string
    {
        return self::PAGES[$key]['slug'];
    }

    public static function route(string $key): string
    {
        return self::PAGES[$key]['route'];
    }

    /**
     * Whether the hub is reachable: the service is switched on and its page
     * is published. Asked several times per request (top strip, header, mobile
     * menu, footer, homepage), so the answer is kept on the request.
     */
    public static function hubIsLive(): bool
    {
        $attributes = request()->attributes;

        if (! $attributes->has('debt_consolidation.hub_live')) {
            $attributes->set('debt_consolidation.hub_live', DebtConsolidationSettings::enabled() && in_array('hub', self::publishedKeys(), true));
        }

        return (bool) $attributes->get('debt_consolidation.hub_live');
    }

    /**
     * The address of a page visitors can reach right now (service on and the
     * page published), else null — so no link ever leads to a 404.
     */
    public static function liveUrl(string $key): ?string
    {
        return DebtConsolidationSettings::enabled() && in_array($key, self::publishedKeys(), true)
            ? route(self::route($key))
            : null;
    }

    /**
     * The keys of the pages whose rows are published, in PAGES order, kept on
     * the request. Whether the service is switched on is a separate question
     * (hubIsLive()), so a signed preview can still link to published pages.
     *
     * @return list<string>
     */
    public static function publishedKeys(): array
    {
        $attributes = request()->attributes;

        if (! $attributes->has('debt_consolidation.published_keys')) {
            $published = Page::query()->published()->whereIn('slug', self::slugs())->pluck('slug')->all();

            $attributes->set('debt_consolidation.published_keys', array_values(array_filter(array_keys(self::PAGES), fn (string $key): bool => in_array(self::slug($key), $published, true))));
        }

        return $attributes->get('debt_consolidation.published_keys');
    }
}
