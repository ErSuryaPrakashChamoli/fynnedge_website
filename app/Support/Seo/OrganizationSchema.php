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
 */
class OrganizationSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $name, ?string $logoUrl): array
    {
        return array_filter([
            /**
             * Multi-typed rather than plain Organization: `priceRange` and
             * `areaServed` are LocalBusiness-level properties, and a loan
             * advisory with a physical office is one. Keeping Organization in
             * the list preserves the `#organization` identity that the rest of
             * the graph refers to.
             */
            '@type' => ['Organization', 'FinancialService'],
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
        ], fn (mixed $value): bool => filled($value));
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
