<?php

use App\Enums\LoanCategory;
use App\Models\Lender;
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

    // The lender chart always shows its "Processing fee" column header, but
    // an offer with no fee data must not repeat the label in its own row.
    expect(substr_count($response->getContent(), 'Processing fee'))->toBe(1);
});

it('orders the lender chart by lowest rate and highlights the best offer in each column', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'show-page-chart-test', 'category' => LoanCategory::PersonalLoan]);
    LenderProduct::factory()->for($product, 'loanProduct')->for(Lender::factory()->state(['name' => 'Costlier Bank']))->create([
        'interest_rate_from' => 11.5,
        'min_amount' => 100000,
        'max_amount' => 5000000,
        'min_tenure_months' => 12,
        'max_tenure_months' => 84,
    ]);
    LenderProduct::factory()->for($product, 'loanProduct')->for(Lender::factory()->state(['name' => 'Cheaper Bank']))->create([
        'interest_rate_from' => 9.25,
        'min_amount' => 50000,
        'max_amount' => 2500000,
        'min_tenure_months' => 12,
        'max_tenure_months' => 60,
    ]);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSeeInOrder(['Cheaper Bank', 'Lowest rate', 'Costlier Bank', 'Highest amount', 'Longest tenure'])
        ->assertSee('₹1 Lakh – ₹50 Lakh')
        ->assertSee('1–7 yrs')
        ->assertSee('Compare Cheaper Bank')
        ->assertSee('Compare selected');
});

it('does not award chart highlights when only one lender is listed', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'show-page-single-lender-test', 'category' => LoanCategory::PersonalLoan]);
    LenderProduct::factory()->for($product, 'loanProduct')->create(['interest_rate_from' => 10.5]);

    $this->get("/loans/{$product->slug}")
        ->assertOk()
        ->assertSee('10.50%')
        ->assertDontSee('Lowest rate')
        ->assertDontSee('Highest amount')
        ->assertDontSee('Longest tenure');
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
