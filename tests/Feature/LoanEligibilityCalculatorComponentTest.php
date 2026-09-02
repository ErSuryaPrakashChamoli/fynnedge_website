<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Livewire\Livewire;

function eligibilityCalculatorSetup(LoanCategory $category, string $slug): LoanProduct
{
    $loanProduct = LoanProduct::factory()->published()->create(['category' => $category, 'slug' => $slug]);
    $lender = Lender::factory()->create(['status' => LenderStatus::Active]);
    $lenderProduct = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $loanProduct->id,
        'status' => LenderStatus::Active,
        'interest_rate_from' => 12,
        'max_tenure_months' => 60,
    ]);
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id]);

    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'label' => 'Minimum age',
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => 'age',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => 21,
    ]);

    return $loanProduct;
}

it('mounts against the published loan product for its category', function () {
    eligibilityCalculatorSetup(LoanCategory::PersonalLoan, 'personal-loan');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSet('category', LoanCategory::PersonalLoan->value)
        ->assertOk();
});

it('leaves the consent checkbox unchecked and links to the terms, privacy and credit report pages', function () {
    eligibilityCalculatorSetup(LoanCategory::PersonalLoan, 'personal-loan');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSet('creditConsent', false)
        ->assertSee('you have read and agree to the')
        ->assertSeeHtml(route('credit-report-terms'))
        ->assertSeeHtml(route('terms'))
        ->assertSeeHtml(route('privacy-policy'));
});

it('blocks evaluation until the customer checks the consent box themselves', function () {
    eligibilityCalculatorSetup(LoanCategory::PersonalLoan, 'personal-loan');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('age', 30)
        ->set('city', 'Mumbai')
        ->set('employmentType', 'salaried')
        ->set('monthlyIncome', 60000)
        ->set('loanAmountRequested', 300000)
        ->set('preferredTenureMonths', 36)
        ->call('evaluate')
        ->assertHasErrors(['creditConsent' => 'accepted'])
        ->assertSet('results', null);
});

it('shows a message instead of a form when no loan product exists for the category', function () {
    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::HomeLoan->value])
        ->assertSee("isn't configured for this loan type yet", false);
});

it('evaluates the entered profile against the real eligibility engine and shows an eligible result', function () {
    eligibilityCalculatorSetup(LoanCategory::PersonalLoan, 'personal-loan');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('age', 30)
        ->set('city', 'Mumbai')
        ->set('employmentType', 'salaried')
        ->set('monthlyIncome', 60000)
        ->set('loanAmountRequested', 300000)
        ->set('preferredTenureMonths', 36)
        ->set('creditConsent', true)
        ->call('evaluate')
        ->assertSet('results.0.status', 'eligible')
        ->assertSee('Apply Now')
        ->assertSee('Talk to a FynnEdge Expert')
        ->assertSeeHtml(route('loans.apply', 'personal-loan'));
});

it('shows a not-eligible result when a mandatory rule fails, with no apply option', function () {
    eligibilityCalculatorSetup(LoanCategory::PersonalLoan, 'personal-loan');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('age', 19)
        ->set('city', 'Mumbai')
        ->set('employmentType', 'salaried')
        ->set('monthlyIncome', 60000)
        ->set('loanAmountRequested', 300000)
        ->set('preferredTenureMonths', 36)
        ->set('creditConsent', true)
        ->call('evaluate')
        ->assertSet('results.0.status', 'not_eligible')
        ->assertDontSee('Apply Now')
        ->assertSee('Talk to a FynnEdge Expert');
});

it('requires the core profile fields before evaluating', function () {
    eligibilityCalculatorSetup(LoanCategory::PersonalLoan, 'personal-loan');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->call('evaluate')
        ->assertHasErrors(['age', 'city', 'monthlyIncome', 'loanAmountRequested', 'preferredTenureMonths', 'creditConsent']);
});
