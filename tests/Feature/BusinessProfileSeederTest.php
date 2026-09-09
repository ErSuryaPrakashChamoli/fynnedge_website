<?php

use App\Models\Setting;
use App\Support\Seo\OrganizationSchema;
use Database\Seeders\BusinessProfileSeeder;

it('fills the structured address the Organization schema needs, which was otherwise blank', function () {
    expect(OrganizationSchema::build('FynnEdge', null))->not->toHaveKey('address');

    $this->seed(BusinessProfileSeeder::class);

    expect(OrganizationSchema::build('FynnEdge', null)['address'])->toBe([
        '@type' => 'PostalAddress',
        'streetAddress' => 'A-70, 1st Floor, Sector 2',
        'addressLocality' => 'Noida',
        'addressRegion' => 'Uttar Pradesh',
        'postalCode' => '201301',
        'addressCountry' => 'IN',
    ]);
});

it('never overwrites a value an admin has already set through the Settings page', function () {
    Setting::set('business_locality', 'Gurugram');
    Setting::set('business_postal_code', '122001');

    $this->seed(BusinessProfileSeeder::class);

    expect(Setting::get('business_locality'))->toBe('Gurugram');
    expect(Setting::get('business_postal_code'))->toBe('122001');
    expect(Setting::get('business_region'))->toBe('Uttar Pradesh');
});

/**
 * Both are public business claims — how wide the service area really is, and
 * a price range for credit FynnEdge advises on but never originates. Neither
 * is the seeder's to invent; they stay blank until an admin fills them in.
 */
it('leaves the service area and price range for an admin rather than inventing them', function () {
    $this->seed(BusinessProfileSeeder::class);

    expect(Setting::get('business_area_served'))->toBeNull();
    expect(Setting::get('business_price_range'))->toBeNull();
    expect(OrganizationSchema::build('FynnEdge', null))
        ->not->toHaveKey('areaServed')
        ->not->toHaveKey('priceRange');
});
