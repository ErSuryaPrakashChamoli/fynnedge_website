<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\Page;
use Illuminate\Support\Facades\Storage;

it('renders the homepage', function () {
    $this->get('/')->assertOk()->assertSee('Simplifying loans');
});

it('renders the loans index with only published products', function () {
    $published = LoanProduct::factory()->published()->create(['name' => 'Home Loan', 'category' => LoanCategory::HomeLoan]);
    $draft = LoanProduct::factory()->create(['name' => 'Draft Product', 'status' => PublishStatus::Draft]);

    $response = $this->get('/loans');

    $response->assertOk();
    $response->assertSee($published->name);
    $response->assertDontSee($draft->name);
});

it('renders a published loan product by slug', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'business-loan']);

    $this->get('/loans/business-loan')->assertOk()->assertSee($product->name);
});

it('404s for a draft loan product on the public site', function () {
    LoanProduct::factory()->create(['slug' => 'draft-product', 'status' => PublishStatus::Draft]);

    $this->get('/loans/draft-product')->assertNotFound();
});

it('shows an initials avatar for a lender with no logo uploaded', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'lender-avatar-test']);
    $lender = Lender::factory()->create(['name' => 'Alpha Finance', 'logo_path' => null, 'status' => LenderStatus::Active]);
    LenderProduct::factory()->create(['loan_product_id' => $product->id, 'lender_id' => $lender->id, 'status' => LenderStatus::Active]);

    $this->get('/loans/lender-avatar-test')
        ->assertOk()
        ->assertSee('Alpha Finance')
        ->assertSee('AF');
});

it('shows the uploaded logo image for a lender that has one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('lenders/logo.png', 'fake-image-content');

    $product = LoanProduct::factory()->published()->create(['slug' => 'lender-logo-test']);
    $lender = Lender::factory()->create(['logo_path' => 'lenders/logo.png', 'status' => LenderStatus::Active]);
    LenderProduct::factory()->create(['loan_product_id' => $product->id, 'lender_id' => $lender->id, 'status' => LenderStatus::Active]);

    $this->get('/loans/lender-logo-test')
        ->assertOk()
        ->assertSee(Storage::disk('public')->url('lenders/logo.png'), false);
});

it('renders the about page from a published CMS page', function () {
    Page::factory()->published()->create(['slug' => 'about', 'title' => 'About FynnEdge']);

    $this->get('/about')->assertOk()->assertSee('About FynnEdge');
});

it('404s the about page when no about content is published', function () {
    Page::query()->delete();

    $this->get('/about')->assertNotFound();
});

it('renders the EMI calculator page with a tab for every EMI-style loan type', function () {
    $response = $this->get('/calculators')->assertOk()->assertSee('EMI Calculator');

    $response->assertSee('Personal Loan')->assertSee('Home Loan')->assertSee('Business Loan')->assertSee('Loan Against Property');
});

it('embeds a matching calculator on a loan product page for each EMI-style category', function (LoanCategory $category) {
    $product = LoanProduct::factory()->published()->create(['category' => $category, 'slug' => "calc-test-{$category->value}"]);

    $this->get("/loans/calc-test-{$category->value}")
        ->assertOk()
        ->assertSee('EMI calculator')
        ->assertSee('Full breakdown, starting this month');
})->with([
    LoanCategory::PersonalLoan,
    LoanCategory::HomeLoan,
    LoanCategory::BusinessLoan,
    LoanCategory::LoanAgainstProperty,
]);

it('does not embed an EMI calculator on the credit card product page', function () {
    $product = LoanProduct::factory()->published()->create(['category' => LoanCategory::CreditCard, 'slug' => 'calc-test-credit-card']);

    $this->get('/loans/calc-test-credit-card')
        ->assertOk()
        ->assertDontSee('EMI calculator')
        ->assertDontSee('Full yearly breakdown');
});

it('renders each legal/company page from a published CMS page by slug', function (string $slug) {
    Page::factory()->published()->create(['slug' => $slug, 'title' => 'Test Title']);

    $this->get("/{$slug}")->assertOk()->assertSee('Test Title');
})->with(['careers', 'grievance', 'privacy-policy', 'terms', 'disclaimer']);

it('404s a legal/company page when it has no published content', function (string $slug) {
    Page::query()->where('slug', $slug)->delete();

    $this->get("/{$slug}")->assertNotFound();
})->with(['careers', 'grievance', 'privacy-policy', 'terms', 'disclaimer']);

it('activates the footer links once the legal pages are published', function () {
    foreach (['careers', 'grievance', 'privacy-policy', 'terms', 'disclaimer'] as $slug) {
        Page::factory()->published()->create(['slug' => $slug]);
    }

    $response = $this->get('/')->assertOk();

    foreach (['careers', 'grievance', 'privacy-policy', 'terms', 'disclaimer'] as $slug) {
        $response->assertSee(route($slug), false);
    }
});
