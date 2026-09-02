<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LenderProduct;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Testimonial;

it('renders a published loan landing page', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan-menu-test']);
    $page = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'slug' => '5-lakh-personal-loan-menu-test',
        'title' => '5 Lakh Personal Loan',
    ]);

    $this->get("/loans/{$product->slug}/{$page->slug}")
        ->assertOk()
        ->assertSee('5 Lakh Personal Loan');
});

it('404s for a draft loan landing page', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'draft-page-product']);
    $page = LoanLandingPage::factory()->for($product, 'loanProduct')->create([
        'slug' => 'draft-landing-page',
        'status' => PublishStatus::Draft,
    ]);

    $this->get("/loans/{$product->slug}/{$page->slug}")->assertNotFound();
});

it('404s when a landing page slug is requested under the wrong loan product', function () {
    $productA = LoanProduct::factory()->published()->create(['slug' => 'product-a']);
    $productB = LoanProduct::factory()->published()->create(['slug' => 'product-b']);
    $page = LoanLandingPage::factory()->published()->for($productA, 'loanProduct')->create(['slug' => 'belongs-to-a']);

    $this->get("/loans/{$productB->slug}/{$page->slug}")->assertNotFound();
});

it('shows a published landing page link in the Loans mega menu', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'slug' => 'menu-visible-page',
        'title' => 'Menu Visible Page',
    ]);

    $this->get('/')->assertOk()->assertSee('Menu Visible Page');
});

it('shows the calculator, lenders, comparison table, why fynnedge, testimonials and faqs on a sub-loan-type page', function () {
    $product = LoanProduct::factory()->published()->state(['category' => LoanCategory::PersonalLoan])->withCalculatorLimits()->create([
        'slug' => 'personal-loan-sub-page-test',
    ]);
    $page = LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'slug' => '5-lakh-personal-loan-sub-page-test',
        'title' => '5 Lakh Personal Loan',
    ]);
    LenderProduct::factory()->for($product, 'loanProduct')->create();
    $faq = Faq::factory()->for($product, 'faqable')->create();
    $testimonial = Testimonial::factory()->create(['loan_category' => LoanCategory::PersonalLoan]);

    $this->get("/loans/{$product->slug}/{$page->slug}")
        ->assertOk()
        ->assertSee('5 Lakh Personal Loan EMI calculator')
        ->assertSee('Lenders offering this product')
        ->assertSee('Apply Now')
        ->assertSee('Compare lenders side by side')
        ->assertSee('Why FynnEdge?')
        ->assertSee($testimonial->quote)
        ->assertSee($faq->question);
});
