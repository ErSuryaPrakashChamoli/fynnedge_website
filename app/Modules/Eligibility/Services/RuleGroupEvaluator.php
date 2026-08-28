<?php

namespace App\Modules\Eligibility\Services;

use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Models\EligibilityRule;

class RuleGroupEvaluator
{
    public function __construct(private readonly RuleConditionEvaluator $conditionEvaluator) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function evaluate(EligibilityRule $rule, array $attributes): bool
    {
        $conditions = $rule->conditions;

        if ($conditions->isEmpty()) {
            return true;
        }

        return $rule->logic === RuleLogic::Or
            ? $conditions->contains(fn ($condition) => $this->conditionEvaluator->evaluate($condition, $attributes))
            : $conditions->every(fn ($condition) => $this->conditionEvaluator->evaluate($condition, $attributes));
    }
}
