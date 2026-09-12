<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\LoanCategory;
use App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * A published product with real calculator limits, so amount validation is
 * tested against the same min/max an admin actually configures.
 */
function enquiryProduct(LoanCategory $category): LoanProduct
{
    return LoanProduct::factory()
        ->published()
        ->state(['category' => $category, 'name' => $category->getLabel(), 'slug' => $category->value])
        ->withCalculatorLimits()
        ->create();
}

function validEnquiry(array $overrides = []): array
{
    return [
        'name' => 'Rahul Sharma',
        'phone' => '9876543210',
        'email' => 'rahul@example.com',
        'loan_amount' => 500000,
        ...$overrides,
    ];
}

beforeEach(function () {
    // The per-number limiter inside RecordEnquiry is a real cache-backed limiter,
    // not one of the test-exempt route limiters — clear the keys the examples
    // below reuse so each starts from zero attempts.
    foreach (range(1, 6) as $productId) {
        RateLimiter::clear("enquiry:9876543210:{$productId}");
    }

    RateLimiter::clear('enquiry:9876543210:general');
});

it('records the loan product, lead source, enquiry source and landing page', function (LoanCategory $category) {
    $product = enquiryProduct($category);

    $response = $this->withHeader('referer', url("/loans/{$product->slug}"))
        ->postJson("/loans/{$product->slug}/enquiry", validEnquiry());

    $response->assertCreated()->assertJsonPath('outcome', 'created');

    $enquiry = ContactEnquiry::query()->sole();

    expect($enquiry->loanProduct->name)->toBe($category->getLabel())
        ->and($enquiry->source)->toBe('website')
        ->and($enquiry->enquiry_source)->toBe($category->getLabel().' Page')
        ->and($enquiry->source_url)->toBe("/loans/{$category->value}")
        ->and($enquiry->enquiry_type)->toBe(EnquiryType::LoanEnquiry)
        ->and($enquiry->status)->toBe(EnquiryStatus::New)
        ->and($enquiry->name)->toBe('Rahul Sharma')
        ->and($enquiry->phone)->toBe('9876543210')
        ->and((float) $enquiry->loan_amount)->toBe(500000.0);
})->with([
    'personal loan' => LoanCategory::PersonalLoan,
    'home loan' => LoanCategory::HomeLoan,
    'business loan' => LoanCategory::BusinessLoan,
]);

it('takes the loan product from the url, never from the submitted body', function () {
    $personalLoan = enquiryProduct(LoanCategory::PersonalLoan);
    $homeLoan = enquiryProduct(LoanCategory::HomeLoan);

    $this->postJson("/loans/{$personalLoan->slug}/enquiry", validEnquiry([
        // Everything a tamperer might try to push into the reporting columns.
        'loan_product_id' => $homeLoan->id,
        'loan_type' => 'Home Loan',
        'enquiry_source' => 'Somewhere Else',
        'source' => 'partner-referral',
    ]))->assertCreated();

    $enquiry = ContactEnquiry::query()->sole();

    expect($enquiry->loan_product_id)->toBe($personalLoan->id)
        ->and($enquiry->enquiry_source)->toBe('Personal Loan Page')
        ->and($enquiry->source)->toBe('website');
});

it('rejects an enquiry for a product that is not published', function () {
    $draft = LoanProduct::factory()->create(['slug' => 'draft-loan']);

    $this->postJson("/loans/{$draft->slug}/enquiry", validEnquiry())->assertNotFound();

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('validates every field on the server', function (array $payload, string $field) {
    $product = enquiryProduct(LoanCategory::PersonalLoan);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry($payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field]);

    expect(ContactEnquiry::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing mobile' => [['phone' => ''], 'phone'],
    'short mobile' => [['phone' => '98765432'], 'phone'],
    'mobile with an invalid leading digit' => [['phone' => '1234567890'], 'phone'],
    'malformed email' => [['email' => 'not-an-email'], 'email'],
    'missing amount' => [['loan_amount' => ''], 'loan_amount'],
    'amount below the product minimum' => [['loan_amount' => 500], 'loan_amount'],
    'amount above the product maximum' => [['loan_amount' => 900000000], 'loan_amount'],
    'honeypot filled' => [['website' => 'https://spam.example'], 'website'],
]);

it('accepts a submission without an email, since it is optional', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry(['email' => '']))->assertCreated();

    expect(ContactEnquiry::query()->sole()->email)->toBeNull();
});

it('normalises a mobile number typed with a country code and separators', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry(['phone' => '+91 98765-43210']))
        ->assertCreated();

    expect(ContactEnquiry::query()->sole()->phone)->toBe('9876543210');
});

it('does not duplicate a lead when the same number enquires about the same product again', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);
    $existing = ContactEnquiry::factory()->forLoanProduct($product)->create(['phone' => '9876543210']);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry())
        ->assertOk()
        ->assertJsonPath('outcome', 'duplicate');

    expect(ContactEnquiry::query()->count())->toBe(1)
        ->and($existing->fresh()->enquiry_count)->toBe(2);
});

it('records a separate lead when the same number enquires about a different product', function () {
    $personalLoan = enquiryProduct(LoanCategory::PersonalLoan);
    $homeLoan = enquiryProduct(LoanCategory::HomeLoan);

    ContactEnquiry::factory()->forLoanProduct($personalLoan)->create(['phone' => '9876543210']);

    $this->postJson("/loans/{$homeLoan->slug}/enquiry", validEnquiry())
        ->assertCreated()
        ->assertJsonPath('outcome', 'created');

    expect(ContactEnquiry::query()->where('phone', '9876543210')->count())->toBe(2)
        ->and(ContactEnquiry::query()->where('loan_product_id', $homeLoan->id)->exists())->toBeTrue();
});

it('reopens a settled enquiry about the same product instead of duplicating it', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);
    $existing = ContactEnquiry::factory()->forLoanProduct($product)->closed()->create(['phone' => '9876543210']);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry())
        ->assertCreated()
        ->assertJsonPath('outcome', 'created');

    expect(ContactEnquiry::query()->count())->toBe(1)
        ->and($existing->fresh())
        ->status->toBe(EnquiryStatus::New)
        ->handled_at->toBeNull()
        ->enquiry_count->toBe(2);
});

it('fills in details it did not have before without overwriting the ones it did', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);
    $existing = ContactEnquiry::factory()->forLoanProduct($product)->create([
        'phone' => '9876543210',
        'name' => 'Original Name',
        'email' => null,
    ]);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry([
        'name' => 'Different Name',
        'email' => 'new@example.com',
    ]))->assertOk();

    expect($existing->fresh())
        ->name->toBe('Original Name')
        ->email->toBe('new@example.com');
});

it('caps repeat submissions per number per product without blocking a different product', function () {
    $personalLoan = enquiryProduct(LoanCategory::PersonalLoan);
    $homeLoan = enquiryProduct(LoanCategory::HomeLoan);

    foreach (range(1, 3) as $attempt) {
        $this->postJson("/loans/{$personalLoan->slug}/enquiry", validEnquiry())->assertSuccessful();
    }

    $this->postJson("/loans/{$personalLoan->slug}/enquiry", validEnquiry())
        ->assertOk()
        ->assertJsonPath('outcome', 'duplicate');

    // The cap is scoped to the product, so a genuine second interest still lands.
    $this->postJson("/loans/{$homeLoan->slug}/enquiry", validEnquiry())
        ->assertCreated()
        ->assertJsonPath('outcome', 'created');
});

it('never returns internal record details to the visitor', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);

    $this->postJson("/loans/{$product->slug}/enquiry", validEnquiry())
        ->assertCreated()
        ->assertJsonStructure(['outcome', 'title', 'message'])
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('public_id')
        ->assertJsonMissingPath('phone')
        ->assertJsonMissingPath('loan_product_id');
});

it('answers a plain browser post with a redirect and a flash message', function () {
    $product = enquiryProduct(LoanCategory::PersonalLoan);

    $this->from("/loans/{$product->slug}")
        ->post("/loans/{$product->slug}/enquiry", validEnquiry())
        ->assertRedirect("/loans/{$product->slug}")
        ->assertSessionHas('quickEnquiryTitle', 'Thank You!');

    expect(ContactEnquiry::query()->count())->toBe(1);
});

it('renders the enquiry form on every loan product page', function () {
    $product = enquiryProduct(LoanCategory::HomeLoan);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Apply for a Home Loan')
        ->assertSee("/loans/{$product->slug}/enquiry", false)
        ->assertSee('loanEnquiryForm(', false)
        ->assertSee('Submit Enquiry');
});

it('shows the loan product and lets an admin filter enquiries by it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $personalLoan = enquiryProduct(LoanCategory::PersonalLoan);
    $homeLoan = enquiryProduct(LoanCategory::HomeLoan);

    $personalLoanEnquiry = ContactEnquiry::factory()->forLoanProduct($personalLoan)->create();
    $homeLoanEnquiry = ContactEnquiry::factory()->forLoanProduct($homeLoan)->create();

    Livewire::actingAs($admin)
        ->test(ListContactEnquiries::class)
        ->assertCanSeeTableRecords([$personalLoanEnquiry, $homeLoanEnquiry])
        ->assertTableColumnStateSet('enquiry_source', 'Personal Loan Page', $personalLoanEnquiry)
        ->assertTableColumnStateSet('source', 'website', $personalLoanEnquiry)
        ->filterTable('loan_product_id', $personalLoan->id)
        ->assertCanSeeTableRecords([$personalLoanEnquiry])
        ->assertCanNotSeeTableRecords([$homeLoanEnquiry]);
});

it('lets an admin filter enquiries by status and date range', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $product = enquiryProduct(LoanCategory::PersonalLoan);

    $today = ContactEnquiry::factory()->forLoanProduct($product)->create();
    $lastYear = ContactEnquiry::factory()->forLoanProduct($product)->create([
        'status' => EnquiryStatus::Converted,
        'created_at' => now()->subYear(),
    ]);

    Livewire::actingAs($admin)
        ->test(ListContactEnquiries::class)
        ->filterTable('status', [EnquiryStatus::Converted->value])
        ->assertCanSeeTableRecords([$lastYear])
        ->assertCanNotSeeTableRecords([$today])
        ->filterTable('status', [])
        ->filterTable('created_at', ['from' => now()->startOfDay()->toDateString()])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$lastYear]);
});

it('shows the full source breakdown on the enquiry detail page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $product = enquiryProduct(LoanCategory::PersonalLoan);
    $enquiry = ContactEnquiry::factory()->forLoanProduct($product)->create(['phone' => '9876543210']);

    $this->actingAs($admin)
        ->get("/admin/contact-enquiries/{$enquiry->public_id}/edit")
        ->assertOk()
        ->assertSee('Enquiry details')
        ->assertSee('Personal Loan Page')
        ->assertSee('/loans/'.$product->slug)
        ->assertSee('9876543210');
});

it('stamps handled_at when an enquiry is converted or rejected', function (EnquiryStatus $status) {
    $enquiry = ContactEnquiry::factory()->create();

    $enquiry->update(['status' => $status]);

    expect($enquiry->fresh()->handled_at)->not->toBeNull();
})->with([
    'converted' => EnquiryStatus::Converted,
    'rejected' => EnquiryStatus::Rejected,
]);
