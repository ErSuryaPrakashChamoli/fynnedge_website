<?php

use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Services\RuleConditionEvaluator;

function condition(string $attribute, RuleOperator $operator, mixed $value): EligibilityRuleCondition
{
    return new EligibilityRuleCondition([
        'attribute' => $attribute,
        'operator' => $operator,
        'value' => $value,
    ]);
}

beforeEach(function () {
    $this->evaluator = new RuleConditionEvaluator;
});

it('evaluates numeric comparisons', function (RuleOperator $operator, int $value, array $passing, array $failing) {
    foreach ($passing as $age) {
        expect($this->evaluator->evaluate(condition('age', $operator, $value), ['age' => $age]))->toBeTrue();
    }
    foreach ($failing as $age) {
        expect($this->evaluator->evaluate(condition('age', $operator, $value), ['age' => $age]))->toBeFalse();
    }
})->with([
    'greater than' => [RuleOperator::GreaterThan, 21, [22, 30], [21, 20]],
    'greater than or equal' => [RuleOperator::GreaterThanOrEqual, 21, [21, 30], [20]],
    'less than' => [RuleOperator::LessThan, 60, [59, 30], [60, 61]],
    'less than or equal' => [RuleOperator::LessThanOrEqual, 60, [60, 30], [61]],
]);

it('fails numeric comparisons when the attribute is missing or non-numeric', function () {
    expect($this->evaluator->evaluate(condition('age', RuleOperator::GreaterThanOrEqual, 21), []))->toBeFalse();
    expect($this->evaluator->evaluate(condition('age', RuleOperator::GreaterThanOrEqual, 21), ['age' => 'unknown']))->toBeFalse();
});

it('evaluates equals and not equals case-insensitively for strings', function () {
    $eq = condition('city', RuleOperator::Equals, 'Delhi');
    expect($this->evaluator->evaluate($eq, ['city' => 'delhi']))->toBeTrue();
    expect($this->evaluator->evaluate($eq, ['city' => 'Mumbai']))->toBeFalse();

    $neq = condition('city', RuleOperator::NotEquals, 'Delhi');
    expect($this->evaluator->evaluate($neq, ['city' => 'Mumbai']))->toBeTrue();
});

it('treats yes/no strings as booleans when comparing to a boolean attribute', function () {
    $condition = condition('has_existing_emis', RuleOperator::Equals, 'yes');

    expect($this->evaluator->evaluate($condition, ['has_existing_emis' => true]))->toBeTrue();
    expect($this->evaluator->evaluate($condition, ['has_existing_emis' => 'yes']))->toBeTrue();
    expect($this->evaluator->evaluate($condition, ['has_existing_emis' => false]))->toBeFalse();
    expect($this->evaluator->evaluate($condition, ['has_existing_emis' => 'no']))->toBeFalse();
});

it('evaluates in and not_in against a list', function () {
    $in = condition('city', RuleOperator::In, ['Delhi', 'Mumbai']);
    expect($this->evaluator->evaluate($in, ['city' => 'Mumbai']))->toBeTrue();
    expect($this->evaluator->evaluate($in, ['city' => 'Chennai']))->toBeFalse();

    $notIn = condition('city', RuleOperator::NotIn, ['Delhi', 'Mumbai']);
    expect($this->evaluator->evaluate($notIn, ['city' => 'Chennai']))->toBeTrue();
    expect($this->evaluator->evaluate($notIn, ['city' => 'Delhi']))->toBeFalse();
});

it('evaluates between inclusively regardless of bound order', function () {
    $condition = condition('foir', RuleOperator::Between, [30, 50]);

    expect($this->evaluator->evaluate($condition, ['foir' => 30]))->toBeTrue();
    expect($this->evaluator->evaluate($condition, ['foir' => 50]))->toBeTrue();
    expect($this->evaluator->evaluate($condition, ['foir' => 40]))->toBeTrue();
    expect($this->evaluator->evaluate($condition, ['foir' => 51]))->toBeFalse();
});

it('evaluates contains and starts_with case-insensitively', function () {
    $contains = condition('employer_name', RuleOperator::Contains, 'tech');
    expect($this->evaluator->evaluate($contains, ['employer_name' => 'Acme Technologies']))->toBeTrue();
    expect($this->evaluator->evaluate($contains, ['employer_name' => 'Acme Corp']))->toBeFalse();

    $startsWith = condition('employer_name', RuleOperator::StartsWith, 'acme');
    expect($this->evaluator->evaluate($startsWith, ['employer_name' => 'Acme Corp']))->toBeTrue();
    expect($this->evaluator->evaluate($startsWith, ['employer_name' => 'Not Acme']))->toBeFalse();
});
