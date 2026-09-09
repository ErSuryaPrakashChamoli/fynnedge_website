<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Fills the structured business-profile Settings that feed the sitewide
 * Organization/FinancialService JSON-LD node (App\Support\Seo\OrganizationSchema).
 *
 * The registered office address already lived in the project as the single
 * free-text `contact_address` Setting, which the footer and contact page
 * render — but OrganizationSchema needs it split into PostalAddress parts, and
 * those were never populated, so every page shipped an Organization node with
 * no address at all. This seeds the same approved address, split up; it does
 * not introduce a second source of truth, and the Settings page remains the
 * place to change it.
 *
 * Deliberately left blank for an admin to fill in, because each is a public
 * business claim this seeder has no authority to invent:
 *   - business_area_served  (which states/regions are actually serviced)
 *   - business_price_range  (FynnEdge is an advisory/DSA, not the lender —
 *                            loan amounts belong to the individual lender)
 */
class BusinessProfileSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const PROFILE = [
        'business_street_address' => 'A-70, 1st Floor, Sector 2',
        'business_locality' => 'Noida',
        'business_region' => 'Uttar Pradesh',
        'business_postal_code' => '201301',
        'business_country' => 'IN',
        'business_description' => 'FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory and distribution business connecting customers with suitable banks and NBFCs across personal loans, home loans, car loans, business loans and loans against property.',
    ];

    public function run(): void
    {
        /**
         * Only fills blanks — an admin who has edited any of these through the
         * Settings page keeps their value, so re-running the seeder on an
         * existing environment can never overwrite an approved edit.
         */
        foreach (self::PROFILE as $key => $value) {
            if (blank(Setting::get($key))) {
                Setting::set($key, $value);
            }
        }
    }
}
