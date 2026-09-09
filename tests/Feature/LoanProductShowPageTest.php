<?php

use App\Enums\LoanCategory;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\Testimonial;

it('shows processing fee and eligibility bullets for an offer that has them set', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'show-page-fee-test', 'category' => LoanCategory::PersonalLoan]);
    LenderProduct::factory()->for($product, 'loanProduct')->create([
        'processing_fee_percent_min' => 1.5,
        'processing_fee_percent_max' => 3,
        'processing_fee_note' => 'Plus applicable GST',
        'min_age' => 21,
        'max_age' => 58,
        'min_credit_score' => 700,
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Processing fee')
        ->assertSee('1.50%–3.00%')
        ->assertSee('Plus applicable GST')
        ->assertSee('Age 21–58 yrs')
        ->assertSee('Credit score 700+');
});

it('does not show a processing fee row on the lender card when unset', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'show-page-no-fee-test', 'category' => LoanCategory::PersonalLoan]);
    LenderProduct::factory()->for($product, 'loanProduct')->create([
        'processing_fee_percent_min' => null,
        'processing_fee_percent_max' => null,
        'processing_fee_note' => null,
        'min_age' => null,
        'max_age' => null,
        'min_credit_score' => null,
        'min_monthly_income' => null,
        'min_employment_vintage_months' => null,
        'employment_types' => null,
    ]);

    $response = $this->get("/loans/{$product->slug}")->assertOk();

    // The bank comparison table always shows a "Processing fee" column header
    // (a fixed table column), but the lender card itself must still hide its
    // own fee row when there's no fee data — so the label should appear only
    // once on the page (the table header), not a second time from the card.
    expect(substr_count($response->getContent(), 'Processing fee'))->toBe(1);
});

it('shows the bank comparison table, apply now link, why fynnedge and matching testimonials', function () {
    $product = LoanProduct::factory()->published()->create([
        'slug' => 'show-page-full-sections-test',
        'category' => LoanCategory::PersonalLoan,
    ]);
    LenderProduct::factory()->for($product, 'loanProduct')->create();

    $generalTestimonial = Testimonial::factory()->create(['loan_category' => null, 'quote' => 'General praise quote.']);
    $matchingTestimonial = Testimonial::factory()->create(['loan_category' => LoanCategory::PersonalLoan, 'quote' => 'Personal loan specific quote.']);
    $otherTestimonial = Testimonial::factory()->create(['loan_category' => LoanCategory::HomeLoan, 'quote' => 'Home loan specific quote.']);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('Compare lenders side by side')
        ->assertSee('Apply Now')
        ->assertSee('Why FynnEdge?')
        ->assertSee($generalTestimonial->quote)
        ->assertSee($matchingTestimonial->quote)
        ->assertDontSee($otherTestimonial->quote);
});
