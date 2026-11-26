<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtScenario>
 */
class DebtScenarioFactory extends Factory
{
    protected $model = DebtScenario::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'label' => 'Personal loan at 12 %',
            'financing_route' => FinancingRoute::PersonalLoan,
            'annual_interest_rate' => 12,
            'tenure_months' => 48,
            'processing_fee_percent' => 1.5,
            'created_by_type' => ActorType::Staff,
        ];
    }
}
