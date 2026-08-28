<?php

use App\Models\LenderProduct;
use App\Modules\Eligibility\Actions\PublishEligibilityRuleSet;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;

function draftRuleSet(): EligibilityRuleSet
{
    return EligibilityRuleSet::factory()->create([
        'lender_product_id' => LenderProduct::factory()->create()->id,
        'status' => EligibilityRuleSetStatus::Draft,
    ]);
}

function addMandatoryRule(EligibilityRuleSet $ruleSet, string $attribute = 'age', mixed $value = 21): EligibilityRule
{
    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
    ]);

    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => $attribute,
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => $value,
    ]);

    return $rule;
}

it('refuses to publish a rule set with no rules', function () {
    $ruleSet = draftRuleSet();

    $errors = app(PublishEligibilityRuleSet::class)->handle($ruleSet);

    expect($errors)->not->toBeEmpty();
    expect($ruleSet->fresh()->status)->toBe(EligibilityRuleSetStatus::Draft);
});

it('refuses to publish a rule with no conditions', function () {
    $ruleSet = draftRuleSet();
    EligibilityRule::factory()->create(['eligibility_rule_set_id' => $ruleSet->id, 'priority' => RulePriority::Mandatory]);

    $errors = app(PublishEligibilityRuleSet::class)->handle($ruleSet);

    expect($errors)->not->toBeEmpty();
});

it('refuses to publish without at least one mandatory rule', function () {
    $ruleSet = draftRuleSet();
    $rule = EligibilityRule::factory()->create(['eligibility_rule_set_id' => $ruleSet->id, 'priority' => RulePriority::Preferred]);
    EligibilityRuleCondition::factory()->create(['eligibility_rule_id' => $rule->id]);

    $errors = app(PublishEligibilityRuleSet::class)->handle($ruleSet);

    expect($errors)->not->toBeEmpty();
});

it('refuses to publish when the effective window is backwards', function () {
    $ruleSet = draftRuleSet();
    $ruleSet->update(['effective_from' => now()->addMonth(), 'effective_until' => now()]);
    addMandatoryRule($ruleSet);

    $errors = app(PublishEligibilityRuleSet::class)->handle($ruleSet->fresh());

    expect($errors)->not->toBeEmpty();
});

it('publishes a well-formed rule set', function () {
    $ruleSet = draftRuleSet();
    addMandatoryRule($ruleSet);

    $errors = app(PublishEligibilityRuleSet::class)->handle($ruleSet);

    expect($errors)->toBeEmpty();
    expect($ruleSet->fresh()->status)->toBe(EligibilityRuleSetStatus::Active);
    expect($ruleSet->fresh()->effective_from)->not->toBeNull();
});

it('archives the previously active version for the same lender product when a new one is published', function () {
    $lenderProduct = LenderProduct::factory()->create();

    $v1 = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id, 'version' => 1, 'status' => EligibilityRuleSetStatus::Draft]);
    addMandatoryRule($v1);
    app(PublishEligibilityRuleSet::class)->handle($v1);

    $v2 = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id, 'version' => 2, 'status' => EligibilityRuleSetStatus::Draft]);
    addMandatoryRule($v2, 'age', 25);
    $errors = app(PublishEligibilityRuleSet::class)->handle($v2);

    expect($errors)->toBeEmpty();
    expect($v1->fresh()->status)->toBe(EligibilityRuleSetStatus::Archived);
    expect($v2->fresh()->status)->toBe(EligibilityRuleSetStatus::Active);
});

it('does not touch rule sets belonging to a different lender product', function () {
    $v1 = draftRuleSet();
    addMandatoryRule($v1);
    app(PublishEligibilityRuleSet::class)->handle($v1);

    $unrelated = draftRuleSet();
    addMandatoryRule($unrelated);
    app(PublishEligibilityRuleSet::class)->handle($unrelated);

    expect($v1->fresh()->status)->toBe(EligibilityRuleSetStatus::Active);
    expect($unrelated->fresh()->status)->toBe(EligibilityRuleSetStatus::Active);
});
