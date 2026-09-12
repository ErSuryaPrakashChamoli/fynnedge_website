<?php

use App\Enums\LoanCategory;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\MarketingSection;
use App\Support\Enquiries\EnquiryFormContent;

function publishedLoanProduct(LoanCategory $category = LoanCategory::PersonalLoan): LoanProduct
{
    return LoanProduct::factory()
        ->published()
        ->state(['category' => $category, 'name' => $category->getLabel(), 'slug' => $category->value])
        ->withCalculatorLimits()
        ->create();
}

it('renders the built-in copy when nothing is published for the placement', function () {
    $product = publishedLoanProduct();

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Apply for a Personal Loan')
        ->assertSee('Instant Personal Loan')
        ->assertSee('Submit Enquiry');
});

it('lets an admin rewrite the loan enquiry copy for every loan page at once', function () {
    $product = publishedLoanProduct(LoanCategory::HomeLoan);

    MarketingSection::factory()->published()->create([
        'placement' => EnquiryFormContent::LOAN_PLACEMENT,
        'heading' => 'Get your :product sanctioned faster',
        'subheading' => ':product in 24 hours',
        'description' => 'Share your details and a :product expert will call you back.',
        'cta_label' => 'Request a callback',
    ]);

    // :product resolves per page, so one row words every loan page correctly.
    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Get your Home Loan sanctioned faster')
        ->assertSee('Home Loan in 24 hours')
        ->assertSee('Share your details and a Home Loan expert will call you back.')
        ->assertSee('Request a callback')
        ->assertDontSee('Apply for a Home Loan');
});

it('ignores an unpublished marketing section', function () {
    $product = publishedLoanProduct();

    MarketingSection::factory()->create([
        'placement' => EnquiryFormContent::LOAN_PLACEMENT,
        'heading' => 'Draft heading that must not ship',
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Apply for a Personal Loan')
        ->assertDontSee('Draft heading that must not ship');
});

it('falls back to the default when an admin clears a field', function () {
    $product = publishedLoanProduct();

    MarketingSection::factory()->published()->create([
        'placement' => EnquiryFormContent::LOAN_PLACEMENT,
        'heading' => 'Apply now for a :product',
        'cta_label' => '',
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Apply now for a Personal Loan')
        ->assertSee('Submit Enquiry');
});

it('resolves :product to a landing page title on a landing page', function () {
    $product = publishedLoanProduct();
    $landingPage = LoanLandingPage::factory()->published()->create([
        'loan_product_id' => $product->id,
        'title' => 'Personal Loan for Wedding',
        'slug' => 'personal-loan-for-wedding',
    ]);

    MarketingSection::factory()->published()->create([
        'placement' => EnquiryFormContent::LOAN_PLACEMENT,
        'heading' => 'Apply for a :product',
    ]);

    $this->get("/loans/{$product->slug}/{$landingPage->slug}")
        ->assertOk()
        ->assertSee('Apply for a Personal Loan for Wedding');
});

it('lets an admin rewrite the homepage quick enquiry copy', function () {
    MarketingSection::factory()->published()->create([
        'placement' => EnquiryFormContent::QUICK_PLACEMENT,
        'heading' => 'Talk to a loan expert today',
        'description' => 'Drop your number, we will call within the hour.',
        'cta_label' => 'Call me back',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Talk to a loan expert today')
        ->assertSee('Drop your number, we will call within the hour.')
        ->assertSee('Call me back')
        ->assertDontSee('Get Started with a Quick Enquiry');
});

it('places the enquiry form above the page content, not below it', function () {
    $product = publishedLoanProduct();

    $html = $this->get("/loans/{$product->slug}")->assertOk()->getContent();

    // The form is inside the hero, so it is on screen when the page loads —
    // it must come before the product detail sections, not after them.
    expect(strpos($html, 'loanEnquiryForm('))->toBeLessThan(strpos($html, 'data-ai-context="Product Features"'));
});

it('aligns loan pages to the same container width as the site header', function () {
    $product = publishedLoanProduct();

    // max-w-7xl px-6 lg:px-8 is what x-site.header and x-site.footer use; a
    // narrower article here is what left the page visibly inset from the nav.
    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('mx-auto max-w-7xl px-6 pb-14 lg:px-8', false)
        ->assertDontSee('max-w-5xl', false);
});
