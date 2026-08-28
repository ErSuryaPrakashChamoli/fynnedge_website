<?php

use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Services\RuleConditionEvaluator;
use App\Modules\Eligibility\Services\RuleGroupEvaluator;
use Illuminate\Support\Collection;

function ruleWithConditions(RuleLogic $logic, array $conditions): EligibilityRule
{
    $rule = new EligibilityRule(['logic' => $logic]);

    $rule->setRelation('conditions', Collection::make($conditions)->map(
        fn (array $condition) => new EligibilityRuleCondition($condition),
    ));

    return $rule;
}

beforeEach(function () {
    $this->evaluator = new RuleGroupEvaluator(new RuleConditionEvaluator);
});

it('requires every condition to pass under AND logic', function () {
    $rule = ruleWithConditions(RuleLogic::And, [
        ['attribute' => 'age', 'operator' => RuleOperator::GreaterThanOrEqual, 'value' => 21],
        ['attribute' => 'city', 'operator' => RuleOperator::Equals, 'value' => 'Delhi'],
    ]);

    expect($this->evaluator->evaluate($rule, ['age' => 25, 'city' => 'Delhi']))->toBeTrue();
    expect($this->evaluator->evaluate($rule, ['age' => 25, 'city' => 'Mumbai']))->toBeFalse();
    expect($this->evaluator->evaluate($rule, ['age' => 19, 'city' => 'Delhi']))->toBeFalse();
});

it('requires only one condition to pass under OR logic', function () {
    $rule = ruleWithConditions(RuleLogic::Or, [
        ['attribute' => 'city', 'operator' => RuleOperator::Equals, 'value' => 'Delhi'],
        ['attribute' => 'city', 'operator' => RuleOperator::Equals, 'value' => 'Mumbai'],
    ]);

    expect($this->evaluator->evaluate($rule, ['city' => 'Delhi']))->toBeTrue();
    expect($this->evaluator->evaluate($rule, ['city' => 'Mumbai']))->toBeTrue();
    expect($this->evaluator->evaluate($rule, ['city' => 'Chennai']))->toBeFalse();
});

it('passes a rule with no conditions by default', function () {
    $rule = ruleWithConditions(RuleLogic::And, []);

    expect($this->evaluator->evaluate($rule, []))->toBeTrue();
});
