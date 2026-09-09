<?php

namespace App\Support\Seo;

use App\Models\Setting;

/**
 * The sitewide Organization node rendered into the layout's JSON-LD `@graph`.
 *
 * Every value comes from a Setting an admin actually filled in and is dropped
 * when blank, so this can never fabricate an address, phone number, service
 * area or price range the business hasn't configured — the same rule the
 * node followed when it only carried name/url/logo.
 *
 * The node's `@type` and any further properties are admin-controlled too, via
 * the `schema_organization_types` / `schema_organization_extra` Settings on the
 * Structured Data page. `@id` stays generated: the WebSite, WebPage and Service
 * nodes all reference it by value.
 */
class OrganizationSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $name, ?string $logoUrl): array
    {
        return SchemaGraph::applyOverrides(array_filter([
            '@type' => self::types(),
            '@id' => SchemaGraph::organizationId(),
            'name' => $name,
            'legalName' => Setting::get('footer_legal_name'),
            'description' => Setting::get('business_description'),
            'url' => url('/'),
            'logo' => $logoUrl,
            'telephone' => Setting::get('contact_phone'),
            'email' => Setting::get('contact_email'),
            'address' => self::postalAddress(),
            'areaServed' => self::areaServed(),
            'sameAs' => SchemaGraph::sameAs(),
            'priceRange' => Setting::get('business_price_range'),
        ], fn (mixed $value): bool => filled($value)), 'schema_organization_extra');
    }

    /**
     * Multi-typed by default rather than plain Organization: `priceRange` and
     * `areaServed` are LocalBusiness-level properties, and a loan advisory with
     * a physical office is one. Whatever an admin enters, Organization is kept
     * in the list — it is what preserves the `#organization` identity the rest
     * of the graph refers to, and dropping it would leave `publisher`,
     * `about` and `provider` pointing at an entity of no declared type.
     *
     * Entered one type per line on the Structured Data page, so an admin never
     * has to hand-write a JSON array. A single type collapses to a string,
     * which is what schema.org expects when there is only one.
     *
     * @return string|array<int, string>
     */
    public static function types(): string|array
    {
        $types = collect(preg_split('/\R/', (string) Setting::get('schema_organization_types')))
            ->map(fn (string $type): string => trim($type))
            ->filter()
            ->values()
            ->all();

        if ($types === []) {
            $types = SchemaGraph::DEFAULT_ORGANIZATION_TYPES;
        }

        if (! in_array('Organization', $types, strict: true)) {
            array_unshift($types, 'Organization');
        }

        return count($types) === 1 ? $types[0] : $types;
    }

    /**
     * @return array<string, string>|null
     */
    private static function postalAddress(): ?array
    {
        $address = array_filter([
            'streetAddress' => Setting::get('business_street_address'),
            'addressLocality' => Setting::get('business_locality'),
            'addressRegion' => Setting::get('business_region'),
            'postalCode' => Setting::get('business_postal_code'),
            'addressCountry' => Setting::get('business_country'),
        ], fn (mixed $value): bool => filled($value));

        return $address === [] ? null : ['@type' => 'PostalAddress', ...$address];
    }

    /**
     * Service areas are entered one per line on the Settings page, so an admin
     * doesn't have to think about JSON or comma escaping. Public because the
     * Service nodes on loan pages reuse the same list.
     *
     * @return array<int, string>|null
     */
    public static function areaServed(): ?array
    {
        $areas = collect(preg_split('/\R/', (string) Setting::get('business_area_served')))
            ->map(fn (string $area): string => trim($area))
            ->filter()
            ->values()
            ->all();

        return $areas === [] ? null : $areas;
    }
}
