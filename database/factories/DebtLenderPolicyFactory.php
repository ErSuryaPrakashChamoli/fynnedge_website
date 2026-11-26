<?php

namespace Database\Factories;

use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtLenderPolicy>
 */
class DebtLenderPolicyFactory extends Factory
{
    protected $model = DebtLenderPolicy::class;

    public function definition(): array
    {
        return [
            'lender_product_id' => LenderProduct::factory(),
            'version' => 1,
            'status' => LenderPolicyStatus::Active,
            'financing_route' => FinancingRoute::PersonalLoan,
            'applicant_types' => ['individual'],
            'accepted_liability_types' => ['personal_loan', 'credit_card_outstanding', 'credit_card_emi'],
            'accepts_own_loans' => true,
            'min_loan_amount' => 100000,
            'max_loan_amount' => 2500000,
            'min_monthly_income' => 30000,
            'min_credit_score' => 700,
            'max_foir_percent' => 60,
            'interest_rate_min' => 10.5,
            'interest_rate_max' => 16,
            'min_tenure_months' => 12,
            'max_tenure_months' => 60,
            'processing_fee_percent' => 1.5,
            'source_name' => 'Lender product note',
            'source_received_on' => now()->subMonth()->toDateString(),
            'effective_from' => now()->subMonth()->toDateString(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => LenderPolicyStatus::Draft]);
    }
}
