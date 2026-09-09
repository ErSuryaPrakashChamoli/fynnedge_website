<?php

namespace App\Support\Seo;

use App\Models\Setting;

/**
 * The single sitewide JSON-LD `@graph` rendered into every public page's head.
 *
 * Nodes are addressed by stable `@id` so crawlers resolve one Organization,
 * one WebSite and one WebPage per URL instead of re-declaring them:
 *
 *   {site}/#organization   the business itself
 *   {site}/#website        the site as a work
 *   {canonical}#webpage    the page being viewed
 *
 * Page-specific nodes (Service, and anything else a controller builds) are
 * appended through $extraNodes so they join the same graph and can point at
 * `#organization` rather than repeating it. BreadcrumbList, FAQPage and
 * Article stay in their own <script> blocks next to the markup they describe —
 * they're already derived from what's visibly on the page, and crawlers merge
 * multiple blocks into one graph anyway.
 *
 * The SHAPE of these three nodes is admin-controlled, not hardcoded: each
 * one's `@type` and an arbitrary set of extra properties come from `schema_*`
 * Settings edited on the Structured Data page. The values below are the
 * defaults a fresh environment emits, and `@id`/`@context` are the two things
 * an override can never touch — losing them would disconnect the graph.
 */
class SchemaGraph
{
    public const DEFAULT_ORGANIZATION_TYPES = ['Organization', 'FinancialService'];

    public const DEFAULT_WEBSITE_TYPE = 'WebSite';

    public const DEFAULT_PAGE_TYPE = 'WebPage';

    public const DEFAULT_LANGUAGE = 'en';

    public static function organizationId(): string
    {
        return url('/').'/#organization';
    }

    public static function websiteId(): string
    {
        return url('/').'/#website';
    }

    /**
     * @param  array<int, array<string, mixed>>  $extraNodes
     * @return array<string, mixed>
     */
    public static function build(
        string $siteName,
        ?string $logoUrl,
        string $pageTitle,
        ?string $pageDescription,
        string $canonicalUrl,
        ?string $ogImageUrl = null,
        ?string $pageType = null,
        array $extraNodes = [],
    ): array {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                OrganizationSchema::build($siteName, $logoUrl),
                self::website($siteName),
                self::webPage(
                    $pageType ?: self::defaultPageType(),
                    $pageTitle,
                    $pageDescription,
                    $canonicalUrl,
                    $ogImageUrl,
                ),
                ...$extraNodes,
            ],
        ];
    }

    public static function defaultPageType(): string
    {
        return self::settingString('schema_default_page_type', self::DEFAULT_PAGE_TYPE);
    }

    public static function language(): string
    {
        return self::settingString('schema_language', self::DEFAULT_LANGUAGE);
    }

    /**
     * @return array<string, mixed>
     */
    private static function website(string $siteName): array
    {
        return self::applyOverrides([
            '@type' => self::settingString('schema_website_type', self::DEFAULT_WEBSITE_TYPE),
            '@id' => self::websiteId(),
            'url' => url('/'),
            'name' => $siteName,
            'publisher' => ['@id' => self::organizationId()],
            'inLanguage' => self::language(),
        ], 'schema_website_extra');
    }

    /**
     * @return array<string, mixed>
     */
    private static function webPage(
        string $pageType,
        string $title,
        ?string $description,
        string $canonicalUrl,
        ?string $ogImageUrl,
    ): array {
        return self::applyOverrides(array_filter([
            '@type' => $pageType,
            '@id' => $canonicalUrl.'#webpage',
            'url' => $canonicalUrl,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => self::websiteId()],
            'about' => ['@id' => self::organizationId()],
            'primaryImageOfPage' => $ogImageUrl ? ['@type' => 'ImageObject', 'url' => $ogImageUrl] : null,
            'inLanguage' => self::language(),
        ], fn (mixed $value): bool => filled($value)), 'schema_webpage_extra');
    }

    /**
     * A loan page describes advisory/distribution work FynnEdge performs, not a
     * credit facility FynnEdge itself originates — so it is a Service whose
     * `provider` is the organization, never a FinancialProduct offered by it.
     * Rate, fee, amount and tenure are deliberately absent: those belong to the
     * individual lender, vary per applicant, and would be fabrication here.
     *
     * @return array<string, mixed>
     */
    public static function service(string $name, string $serviceType, string $url, ?string $description = null): array
    {
        return array_filter([
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $name,
            'serviceType' => $serviceType,
            'url' => $url,
            'description' => $description,
            'provider' => ['@id' => self::organizationId()],
            'areaServed' => OrganizationSchema::areaServed(),
        ], fn (mixed $value): bool => filled($value));
    }

    /**
     * Profile links an admin has actually filled in, used as `sameAs` on the
     * Organization node so answer engines can tie the site to those profiles.
     *
     * @return array<int, string>|null
     */
    public static function sameAs(): ?array
    {
        $profiles = collect(['social_instagram', 'social_facebook', 'social_linkedin', 'social_x'])
            ->map(fn (string $key): mixed => Setting::get($key))
            ->filter(fn (mixed $url): bool => filled($url))
            ->values()
            ->all();

        return $profiles === [] ? null : $profiles;
    }

    /**
     * Merges an admin's extra properties for a node over the generated ones.
     *
     * Admin values win on a key collision — that is the point of the feature —
     * except for `@id`, which every other node in the graph references by
     * value. Overriding it would leave `isPartOf`/`about`/`provider` pointing
     * at an id nothing declares, which is worse than no override at all.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function applyOverrides(array $node, string $settingKey): array
    {
        $overrides = Setting::get($settingKey);

        if (! is_array($overrides) || $overrides === []) {
            return $node;
        }

        unset($overrides['@id'], $overrides['@context']);

        return array_filter([...$node, ...$overrides], fn (mixed $value): bool => filled($value));
    }

    private static function settingString(string $key, string $default): string
    {
        $value = Setting::get($key);

        return filled($value) && is_string($value) ? trim($value) : $default;
    }
}
