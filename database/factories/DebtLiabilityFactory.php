<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtLiability>
 */
class DebtLiabilityFactory extends Factory
{
    protected $model = DebtLiability::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'liability_type' => LiabilityType::PersonalLoan,
            'lender_name' => $this->faker->randomElement(['HDFC Bank', 'ICICI Bank', 'Bajaj Finance', 'Tata Capital']),
            'is_secured' => false,
            'repayment_structure' => RepaymentStructure::Amortising,
            'outstanding_principal' => 500000,
            'interest_rate' => 18,
            'remaining_months' => 36,
            'consolidation_mode' => ConsolidationMode::Full,
        ];
    }

    public function creditCard(float $outstanding = 150000, ?float $monthlyPayment = 15000): static
    {
        return $this->state(fn (): array => [
            'liability_type' => LiabilityType::CreditCardOutstanding,
            'repayment_structure' => RepaymentStructure::Revolving,
            'outstanding_principal' => $outstanding,
            'interest_rate' => 42,
            'monthly_payment' => $monthlyPayment,
            'remaining_months' => null,
        ]);
    }

    public function kept(): static
    {
        return $this->state(fn (): array => ['consolidation_mode' => ConsolidationMode::Exclude]);
    }
}
