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

it('shows each loan calculator page its own About content, independent of the others', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    seedCalculatorProduct(LoanCategory::HomeLoan);
    CalculatorPage::factory()->create(['calculator_key' => 'emi/personal-loan', 'title' => null, 'body' => '<p>TEST ABOUT PERSONAL LOAN EMI</p>']);
    CalculatorPage::factory()->create(['calculator_key' => 'emi/home-loan', 'title' => null, 'body' => '<p>TEST ABOUT HOME LOAN EMI</p>']);
    CalculatorPage::factory()->create(['calculator_key' => 'eligibility/home-loan', 'title' => null, 'body' => '<p>TEST ABOUT HOME LOAN ELIGIBILITY</p>']);

    $this->get('/calculators/emi/personal-loan')
        ->assertOk()
        ->assertSee('About the Personal Loan')
        ->assertSee('TEST ABOUT PERSONAL LOAN EMI')
        ->assertDontSee('TEST ABOUT HOME LOAN');

    $this->get('/calculators/emi/home-loan')
        ->assertOk()
        ->assertSee('TEST ABOUT HOME LOAN EMI')
        ->assertDontSee('TEST ABOUT HOME LOAN ELIGIBILITY')
        ->assertDontSee('TEST ABOUT PERSONAL LOAN EMI');

    $this->get('/calculators/eligibility/home-loan')
        ->assertOk()
        ->assertSee('TEST ABOUT HOME LOAN ELIGIBILITY')
        ->assertDontSee('TEST ABOUT HOME LOAN EMI');
});

it('prefers the calculator page About content over the loan product calculator_explanation', function () {
    LoanProduct::factory()->published()->create([
        'category' => LoanCategory::PersonalLoan,
        'calculator_explanation' => '<p>Shared loan explanation.</p>',
    ]);
    CalculatorPage::factory()->create(['calculator_key' => 'eligibility/personal-loan', 'body' => '<p>Eligibility-only copy.</p>']);

    $this->get('/calculators/eligibility/personal-loan')
        ->assertOk()
        ->assertSee('Eligibility-only copy.')
        ->assertDontSee('Shared loan explanation.');
});

it('headings the About section with the admin title, else the calculator name', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'sip', 'title' => null, 'body' => '<p>SIP copy.</p>']);
    CalculatorPage::factory()->create(['calculator_key' => 'gst', 'title' => 'Understanding GST', 'body' => '<p>GST copy.</p>']);

    $this->get('/calculators/sip')->assertOk()->assertSee('About the SIP Calculator');
    $this->get('/calculators/gst')->assertOk()->assertSee('Understanding GST')->assertDontSee('About the GST Calculator');
});

it('hides the About section when the saved content is only empty editor markup', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'sip', 'title' => 'About SIPs', 'body' => '<p></p><p>&nbsp;</p>']);

    $this->get('/calculators/sip')
        ->assertOk()
        ->assertDontSee('About SIPs');
});

it('shows the About section on the Flexi Hybrid EMI calculator page', function () {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    CalculatorPage::factory()->create(['calculator_key' => 'emi/flexi-hybrid-term-loan', 'title' => null, 'body' => '<p>TEST ABOUT FLEXI HYBRID</p>']);

    $this->get('/calculators/emi/flexi-hybrid-term-loan')
        ->assertOk()
        ->assertSee('About the Flexi Hybrid Term Loan EMI Calculator')
        ->assertSee('TEST ABOUT FLEXI HYBRID');
});

it('gives one loan calculator page its own headline and introduction without changing the others', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    seedCalculatorProduct(LoanCategory::HomeLoan);
    CalculatorPage::factory()->create([
        'calculator_key' => 'emi/home-loan',
        'heading' => 'Unique home loan EMI headline',
        'description' => 'Unique home loan EMI introduction.',
        'meta_title' => 'Unique home loan EMI title',
    ]);

    $this->get('/calculators/emi/home-loan')
        ->assertOk()
        ->assertSee('Unique home loan EMI headline')
        ->assertSee('Unique home loan EMI introduction.')
        ->assertSee('Unique home loan EMI title');

    $this->get('/calculators/emi/personal-loan')
        ->assertOk()
        ->assertDontSee('Unique home loan EMI headline')
        ->assertSee('Personal Loan EMI Calculator');
});

it('keeps the shared wording for fields the calculator page leaves blank', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'gst', 'heading' => 'Unique GST headline', 'description' => null]);

    $this->get('/calculators/gst')
        ->assertOk()
        ->assertSee('Unique GST headline')
        ->assertSee('Add GST to a base amount, or work out the base amount and GST already included in a total.');
});

it('links the loan type tabs on the EMI calculator page to each loan type\'s own page', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    seedCalculatorProduct(LoanCategory::HomeLoan);
    CalculatorPage::factory()->create(['calculator_key' => 'emi/home-loan', 'heading' => 'Unique home loan EMI headline']);

    $this->get('/calculators/emi/home-loan')
        ->assertOk()
        ->assertSee('href="'.route('calculators.emi', LoanCategory::PersonalLoan->value).'"', false)
        ->assertDontSee("selectCategory('personal-loan')", false);
});
