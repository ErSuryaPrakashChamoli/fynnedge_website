<?php

namespace Database\Factories;

use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EligibilityRuleCondition>
 */
class EligibilityRuleConditionFactory extends Factory
{
    protected $model = EligibilityRuleCondition::class;

    public function definition(): array
    {
        return [
            'eligibility_rule_id' => EligibilityRule::factory(),
            'attribute' => 'age',
            'operator' => RuleOperator::GreaterThanOrEqual,
            'value' => 21,
            'order' => 0,
        ];
    }
}
