<?php

use App\Enums\EnquiryType;
use App\Enums\LoanCategory;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A published product with its real seeded limits, so per-product amount
 * validation is tested against the ranges an admin actually configures.
 */
function quickPageProduct(LoanCategory $category): LoanProduct
{
    return LoanProduct::factory()
        ->published()
        ->state(['category' => $category, 'name' => $category->getLabel(), 'slug' => $category->value])
        ->withCalculatorLimits()
        ->create();
}

function quickPageEnquiry(LoanProduct $product, array $overrides = []): array
{
    return [
        'loan_product' => $product->slug,
        'name' => 'Rahul Sharma',
        'phone' => '9876543210',
        'email' => 'rahul@example.com',
        'loan_amount' => 500000,
        ...$overrides,
    ];
}

beforeEach(function () {
    foreach (range(1, 6) as $productId) {
        RateLimiter::clear("enquiry:9876543210:{$productId}");
    }
});

it('links to the quick enquiry page from the homepage hero', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('quick-enquiry.show'), false)
        ->assertSeeInOrder(['Check Your Eligibility', 'Explore Loan Products', 'Quick Enquiry']);
});

it('offers only currently published loan products in the loan type dropdown', function () {
    quickPageProduct(LoanCategory::PersonalLoan);
    quickPageProduct(LoanCategory::HomeLoan);
    LoanProduct::factory()->create(['name' => 'Draft Loan', 'slug' => 'draft-loan']);

    $html = $this->get('/quick-enquiry')
        ->assertOk()
        ->assertSee('name="loan_product"', false)
        ->assertSee('Personal Loan · from 10.49%')
        ->assertSee('Home Loan · from 7%')
        ->assertDontSee('Draft Loan')
        ->assertSee(route('quick-enquiry.apply'), false)
        ->getContent();

    // The dropdown joins name, phone, email and amount with the same fixed-width affix.
    expect(substr_count($html, 'w-11 shrink-0 select-none'))->toBe(5);
});

it('preselects the loan type named in the query string and ignores an unknown one', function () {
    $homeLoan = quickPageProduct(LoanCategory::HomeLoan);

    $this->get("/quick-enquiry?loan={$homeLoan->slug}")
        ->assertSee("value=\"{$homeLoan->slug}\" selected", false)
        ->assertSee('Get up to');

    $this->get('/quick-enquiry?loan=made-up-loan')
        ->assertSee('value="" selected', false)
        ->assertSee('Select a loan type to see the amount range');
});

it('puts the enquiry form ahead of the page copy so a phone visitor lands on it', function () {
    quickPageProduct(LoanCategory::PersonalLoan);

    $html = $this->get('/quick-enquiry')->assertOk()->getContent();

    // DOM order is what a phone shows (lg:order-last only moves it on desktop).
    expect(strpos($html, 'loanEnquiryForm('))->toBeLessThan(strpos($html, 'Tell us what you need.'));
});

it('records the lead against the chosen product and reports it as the quick enquiry page', function () {
    $personalLoan = quickPageProduct(LoanCategory::PersonalLoan);
    $homeLoan = quickPageProduct(LoanCategory::HomeLoan);

    $this->withHeader('referer', url('/quick-enquiry'))
        ->postJson('/quick-enquiry/apply', quickPageEnquiry($homeLoan, [
            'loan_amount' => '40,00,000',
            // Reporting columns a tamperer might try to write directly.
            'loan_product_id' => $personalLoan->id,
            'enquiry_source' => 'Somewhere Else',
            'source' => 'partner-referral',
        ]))
        ->assertCreated()
        ->assertJsonPath('outcome', 'created');

    expect(ContactEnquiry::query()->sole())
        ->loan_product_id->toBe($homeLoan->id)
        ->enquiry_type->toBe(EnquiryType::LoanEnquiry)
        ->enquiry_source->toBe('Quick Enquiry Page')
        ->source->toBe('website')
        ->source_url->toBe('/quick-enquiry')
        ->phone->toBe('9876543210')
        ->and((float) ContactEnquiry::query()->sole()->loan_amount)->toBe(4000000.0);
});

it('rejects a loan type that is missing or not currently published', function (Closure $slug) {
    $this->postJson('/quick-enquiry/apply', [...quickPageEnquiry(quickPageProduct(LoanCategory::PersonalLoan)), 'loan_product' => $slug()])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan_product' => 'Please select a loan type.']);

    expect(ContactEnquiry::query()->count())->toBe(0);
})->with([
    'missing' => fn () => fn () => '',
    'unknown' => fn () => fn () => 'made-up-loan',
    'draft' => fn () => fn () => LoanProduct::factory()->create(['slug' => 'draft-loan'])->slug,
    'expired' => fn () => fn () => LoanProduct::factory()->published()->create(['slug' => 'old-loan', 'expires_at' => now()->subDay()])->slug,
]);

it('judges the amount against the chosen product range, not a fixed one', function () {
    $personalLoan = quickPageProduct(LoanCategory::PersonalLoan);
    $homeLoan = quickPageProduct(LoanCategory::HomeLoan);

    // ₹1,00,000 is below a Home Loan's ₹5,00,000 minimum but fine for a Personal Loan.
    $this->postJson('/quick-enquiry/apply', quickPageEnquiry($homeLoan, ['loan_amount' => 100000]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan_amount' => 'Enter an amount between ₹5,00,000']);

    $this->postJson('/quick-enquiry/apply', quickPageEnquiry($personalLoan, ['loan_amount' => 100000]))
        ->assertCreated();
});

it('validates the shared enquiry fields on the server', function (array $payload, string $field) {
    $product = quickPageProduct(LoanCategory::PersonalLoan);

    $this->postJson('/quick-enquiry/apply', quickPageEnquiry($product, $payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field]);

    expect(ContactEnquiry::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid mobile' => [['phone' => '1234567890'], 'phone'],
    'honeypot filled' => [['website' => 'https://spam.example'], 'website'],
]);

it('answers a plain browser post with a redirect back to the page', function () {
    $product = quickPageProduct(LoanCategory::PersonalLoan);

    $this->from('/quick-enquiry')
        ->post('/quick-enquiry/apply', quickPageEnquiry($product))
        ->assertRedirect('/quick-enquiry')
        ->assertSessionHas('quickEnquiryTitle', 'Thank You!');

    expect(ContactEnquiry::query()->count())->toBe(1);
});

it('rate limits the endpoint like the other enquiry forms', function () {
    expect(app('router')->getRoutes()->getByName('quick-enquiry.apply')->gatherMiddleware())
        ->toContain('throttle:enquiry-forms');
});

it('points visitors to the contact page when no loan products are published', function () {
    $this->get('/quick-enquiry')
        ->assertOk()
        ->assertSee('Online enquiries are paused')
        ->assertDontSee('name="loan_product"', false);
});
