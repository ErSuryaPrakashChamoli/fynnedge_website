<?php

use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\Eligibility\Models\Employer;
use App\Modules\Eligibility\Models\EmployerCategory;
use App\Modules\Eligibility\Models\EmployerRating;
use App\Modules\Eligibility\Services\EligibilityEngine;
use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;

function eligibilitySetup(): array
{
    $loanProduct = LoanProduct::factory()->published()->create();
    $lender = Lender::factory()->create(['status' => LenderStatus::Active]);
    $lenderProduct = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $loanProduct->id,
        'status' => LenderStatus::Active,
        'interest_rate_from' => 12,
        'max_tenure_months' => 60,
    ]);
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id]);

    return compact('loanProduct', 'lender', 'lenderProduct', 'ruleSet');
}

function addRule(EligibilityRuleSet $ruleSet, string $label, RulePriority $priority, string $attribute, RuleOperator $operator, mixed $value): EligibilityRule
{
    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'label' => $label,
        'priority' => $priority,
        'logic' => RuleLogic::And,
    ]);

    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => $attribute,
        'operator' => $operator,
        'value' => $value,
    ]);

    return $rule;
}

function sessionWithJourneyResponses(LoanProduct $loanProduct, array $responses): JourneySession
{
    $session = JourneySession::factory()->create(['loan_product_id' => $loanProduct->id]);

    foreach ($responses as $key => $value) {
        JourneyResponse::create(['journey_session_id' => $session->id, 'field_key' => $key, 'value' => $value]);
    }

    return $session;
}

it('marks a session eligible when every mandatory rule passes', function () {
    ['loanProduct' => $loanProduct, 'ruleSet' => $ruleSet] = eligibilitySetup();
    addRule($ruleSet, 'Minimum age', RulePriority::Mandatory, 'age', RuleOperator::GreaterThanOrEqual, 21);

    $session = sessionWithJourneyResponses($loanProduct, [
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'monthly_income' => 60000,
    ]);

    $results = app(EligibilityEngine::class)->evaluateSession($session);

    expect($results)->toHaveCount(1);
    expect($results->first()->status)->toBe(EligibilityStatus::Eligible);
});

it('marks a session not eligible when a mandatory rule fails', function () {
    ['loanProduct' => $loanProduct, 'ruleSet' => $ruleSet] = eligibilitySetup();
    addRule($ruleSet, 'Minimum age', RulePriority::Mandatory, 'age', RuleOperator::GreaterThanOrEqual, 21);

    $session = sessionWithJourneyResponses($loanProduct, [
        'date_of_birth' => now()->subYears(19)->toDateString(),
    ]);

    $result = app(EligibilityEngine::class)->evaluateSession($session)->first();

    expect($result->status)->toBe(EligibilityStatus::NotEligible);
    expect($result->reasons->first()->passed)->toBeFalse();
});

it('does not block eligibility when only a preferred rule fails', function () {
    ['loanProduct' => $loanProduct, 'ruleSet' => $ruleSet] = eligibilitySetup();
    addRule($ruleSet, 'Preferred income', RulePriority::Preferred, 'monthly_income', RuleOperator::GreaterThanOrEqual, 100000);

    $session = sessionWithJourneyResponses($loanProduct, ['monthly_income' => 40000]);

    $result = app(EligibilityEngine::class)->evaluateSession($session)->first();

    expect($result->status)->toBe(EligibilityStatus::Eligible);
    expect($result->reasons->first()->passed)->toBeFalse();
});

it('resolves employer category per lender and uses it in rules', function () {
    ['loanProduct' => $loanProduct, 'lender' => $lender, 'ruleSet' => $ruleSet] = eligibilitySetup();
    $category = EmployerCategory::factory()->create(['lender_id' => $lender->id, 'key' => 'A']);
    $employer = Employer::factory()->create(['name' => 'Acme Corp']);
    EmployerRating::create(['employer_id' => $employer->id, 'lender_id' => $lender->id, 'employer_category_id' => $category->id]);

    addRule($ruleSet, 'Approved employer', RulePriority::Mandatory, 'employer_category', RuleOperator::Equals, 'A');

    $session = sessionWithJourneyResponses($loanProduct, ['company_name' => 'Acme Corp']);

    $result = app(EligibilityEngine::class)->evaluateSession($session)->first();

    expect($result->status)->toBe(EligibilityStatus::Eligible);
});

it('computes and stores FOIR against the lender own rate', function () {
    ['loanProduct' => $loanProduct, 'ruleSet' => $ruleSet] = eligibilitySetup();
    addRule($ruleSet, 'FOIR limit', RulePriority::Mandatory, 'foir', RuleOperator::LessThanOrEqual, 50);

    $session = sessionWithJourneyResponses($loanProduct, [
        'monthly_income' => 70000,
        'loan_amount' => 300000,
        'preferred_tenure_months' => 36,
    ]);

    $result = app(EligibilityEngine::class)->evaluateSession($session)->first();

    expect((float) $result->foir)->toBeGreaterThan(0);
});

it('is idempotent — re-evaluating updates the existing result instead of duplicating it', function () {
    ['loanProduct' => $loanProduct, 'ruleSet' => $ruleSet] = eligibilitySetup();
    addRule($ruleSet, 'Minimum age', RulePriority::Mandatory, 'age', RuleOperator::GreaterThanOrEqual, 21);

    $session = sessionWithJourneyResponses($loanProduct, ['date_of_birth' => now()->subYears(30)->toDateString()]);

    $engine = app(EligibilityEngine::class);
    $first = $engine->evaluateSession($session)->first();
    $second = $engine->evaluateSession($session)->first();

    expect($first->id)->toBe($second->id);
    expect(EligibilityResult::query()->count())->toBe(1);
});

it('does not claim eligibility when no rule set has been configured yet', function () {
    $loanProduct = LoanProduct::factory()->published()->create();
    $lender = Lender::factory()->create(['status' => LenderStatus::Active]);
    LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $loanProduct->id,
        'status' => LenderStatus::Active,
    ]);

    $session = sessionWithJourneyResponses($loanProduct, []);

    $result = app(EligibilityEngine::class)->evaluateSession($session)->first();

    expect($result->status)->toBe(EligibilityStatus::NotEligible);
});

it('evaluates a hand-entered attribute bag for the admin tester without persisting anything', function () {
    ['loanProduct' => $loanProduct, 'ruleSet' => $ruleSet] = eligibilitySetup();
    addRule($ruleSet, 'Minimum age', RulePriority::Mandatory, 'age', RuleOperator::GreaterThanOrEqual, 21);

    $rows = app(EligibilityEngine::class)->evaluateAttributesForLoanProduct(['age' => 30], $loanProduct);

    expect($rows)->toHaveCount(1);
    expect($rows->first()['evaluation']->status)->toBe(EligibilityStatus::Eligible);
    expect(EligibilityResult::query()->count())->toBe(0);
});
