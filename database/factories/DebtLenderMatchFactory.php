<?php

namespace Database\Factories;

use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\Enums\AdvisorReviewStatus;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtLenderMatch>
 */
class DebtLenderMatchFactory extends Factory
{
    protected $model = DebtLenderMatch::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'lender_product_id' => LenderProduct::factory(),
            'match_status' => LenderMatchStatus::PotentialMatch,
            'reasons' => [['code' => 'applicant_type', 'passed' => true, 'message' => 'Accepts individuals']],
            'advisor_status' => AdvisorReviewStatus::Pending,
            'evaluated_at' => now(),
        ];
    }
}
