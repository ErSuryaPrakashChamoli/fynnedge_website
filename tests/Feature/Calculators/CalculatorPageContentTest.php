<?php

use App\Enums\LoanCategory;
use App\Models\CalculatorPage;
use App\Models\LoanProduct;

it('shows the admin-written body on the fixed deposit calculator page', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'fixed-deposit', 'body' => '<p>Unique FD marketing copy.</p>']);

    $this->get('/calculators/fixed-deposit')
        ->assertOk()
        ->assertSee('Unique FD marketing copy.', false);
});

it('shows the admin-written body on the SIP calculator page', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'sip', 'body' => '<p>Unique SIP marketing copy.</p>']);

    $this->get('/calculators/sip')
        ->assertOk()
        ->assertSee('Unique SIP marketing copy.', false);
});

it('shows the admin-written body on the daily SIP calculator page', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'daily-sip', 'body' => '<p>Unique daily SIP marketing copy.</p>']);

    $this->get('/calculators/daily-sip')
        ->assertOk()
        ->assertSee('Unique daily SIP marketing copy.', false);
});

it('shows the admin-written body on the GST calculator page', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'gst', 'body' => '<p>Unique GST marketing copy.</p>']);

    $this->get('/calculators/gst')
        ->assertOk()
        ->assertSee('Unique GST marketing copy.', false);
});

it('offers a general eligibility check CTA on non-loan calculator pages even without any content configured', function () {
    $this->get('/calculators/gst')
        ->assertOk()
        ->assertSee('Check Your Eligibility')
        ->assertSee(route('eligibility.index'), false);
});

it('shows the loan product calculator_explanation and an apply CTA on the eligibility calculator page', function () {
    $product = LoanProduct::factory()->published()->create([
        'category' => LoanCategory::PersonalLoan,
        'calculator_explanation' => '<p>Unique eligibility explanation copy.</p>',
    ]);

    $this->get('/calculators/eligibility/personal-loan')
        ->assertOk()
        ->assertSee('Unique eligibility explanation copy.', false)
        ->assertSee('Apply for this loan')
        ->assertSee(route('loans.apply', $product), false);
});

it('shows the loan product calculator_explanation and both CTAs on the prepayment calculator page', function () {
    $product = LoanProduct::factory()->published()->create([
        'category' => LoanCategory::PersonalLoan,
        'calculator_explanation' => '<p>Unique prepayment explanation copy.</p>',
        'max_amount' => 1_000_000,
        'max_tenure_months' => 60,
        'min_interest_rate' => 10,
        'max_interest_rate' => 20,
    ]);

    $this->get('/calculators/prepayment/personal-loan')
        ->assertOk()
        ->assertSee('Unique prepayment explanation copy.', false)
        ->assertSee('Apply for this loan')
        ->assertSee('Check Your Eligibility')
        ->assertSee(route('loans.apply', $product), false);
});
