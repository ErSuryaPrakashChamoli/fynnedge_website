<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Models\Setting;

/**
 * @return array<string, mixed>|null
 */
function organizationNode(string $html): ?array
{
    $graph = collect(explode('<script type="application/ld+json">', $html))
        ->skip(1)
        ->map(fn (string $chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn (?array $data) => isset($data['@graph']));

    return collect($graph['@graph'] ?? [])
        ->first(fn (array $node) => str_ends_with($node['@id'] ?? '', '#organization'));
}

it('builds the Organization node from the business profile settings', function () {
    Setting::set('business_description', 'Loan advisory firm in Delhi NCR specialising in personal and flexi loans.');
    Setting::set('contact_phone', '+91-8800-250320');
    Setting::set('contact_email', 'care@fynnedge.com');
    Setting::set('business_street_address', 'Sector 02');
    Setting::set('business_locality', 'Noida');
    Setting::set('business_region', 'Uttar Pradesh');
    Setting::set('business_postal_code', '201301');
    Setting::set('business_country', 'IN');
    Setting::set('business_price_range', '₹25,000 - ₹30,00,000');

    $node = organizationNode($this->get('/')->assertOk()->getContent());

    expect($node['@type'])->toBe(['Organization', 'FinancialService']);
    expect($node['description'])->toStartWith('Loan advisory firm in Delhi NCR');
    expect($node['telephone'])->toBe('+91-8800-250320');
    expect($node['email'])->toBe('care@fynnedge.com');
    expect($node['priceRange'])->toBe('₹25,000 - ₹30,00,000');
    expect($node['address'])->toBe([
        '@type' => 'PostalAddress',
        'streetAddress' => 'Sector 02',
        'addressLocality' => 'Noida',
        'addressRegion' => 'Uttar Pradesh',
        'postalCode' => '201301',
        'addressCountry' => 'IN',
    ]);
});

it('splits areas served on newlines and trims them', function () {
    Setting::set('business_area_served', "India\nDelhi NCR\n  Karnataka  \n\nMaharashtra\n");

    expect(organizationNode($this->get('/')->getContent())['areaServed'])
        ->toBe(['India', 'Delhi NCR', 'Karnataka', 'Maharashtra']);
});

it('omits every business profile property that has not been configured, rather than guessing one', function () {
    $node = organizationNode($this->get('/')->assertOk()->getContent());

    expect($node)->not->toBeNull();
    expect($node['name'])->toBe('FynnEdge');
    expect($node)->not->toHaveKey('address');
    expect($node)->not->toHaveKey('telephone');
    expect($node)->not->toHaveKey('areaServed');
    expect($node)->not->toHaveKey('priceRange');
    expect($node)->not->toHaveKey('description');
});

it('omits the address entirely when no address part is filled in', function () {
    Setting::set('business_country', 'IN');
    Setting::set('business_street_address', null);

    expect(organizationNode($this->get('/')->getContent())['address'])
        ->toBe(['@type' => 'PostalAddress', 'addressCountry' => 'IN']);
});

it('marks up a loan product page with Service microdata and AI context labels', function () {
    LoanProduct::factory()->published()->create([
        'slug' => 'personal-loan',
        'category' => LoanCategory::PersonalLoan,
        'name' => 'Instant Personal Loans',
        'summary' => 'Fast personal loans matched to your profile.',
        'eligibility_points' => ['Applicants must be between 21 and 60 years old.'],
        'benefits' => ['Quick disbursal'],
    ]);

    $this->get('/loans/personal-loan')
        ->assertOk()
        ->assertSee('itemtype="https://schema.org/Service"', false)
        ->assertSee('itemprop="name"', false)
        ->assertSee('itemprop="description"', false)
        ->assertSee('data-ai-context="Product Eligibility"', false)
        ->assertSee('data-ai-context="Product Benefits"', false);
});

it('keeps the flexi hybrid jump-link anchor working on the semantic article wrapper', function () {
    $product = LoanProduct::factory()->published()->create([
        'slug' => 'flexi-hybrid-term-loan',
        'category' => LoanCategory::FlexiHybridTermLoan,
        'status' => PublishStatus::Published,
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('id="flexi-hybrid-details"', false)
        ->assertSee('itemtype="https://schema.org/Service"', false);
});
