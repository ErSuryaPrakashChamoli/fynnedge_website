<?php

namespace Database\Factories;

use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EligibilityRule>
 */
class EligibilityRuleFactory extends Factory
{
    protected $model = EligibilityRule::class;

    public function definition(): array
    {
        return [
            'eligibility_rule_set_id' => EligibilityRuleSet::factory(),
            'label' => $this->faker->words(3, true),
            'priority' => RulePriority::Mandatory,
            'logic' => RuleLogic::And,
            'customer_message' => null,
            'order' => 0,
        ];
    }
}
