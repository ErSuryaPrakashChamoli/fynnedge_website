<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtLenderApplication>
 */
class DebtLenderApplicationFactory extends Factory
{
    protected $model = DebtLenderApplication::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'lender_name' => 'HDFC Bank',
            'product_name' => 'Personal Loan',
            'status' => LenderApplicationStatus::NotSubmitted,
            'applied_amount' => 650000,
        ];
    }

    public function disbursed(): static
    {
        return $this->state(fn (): array => [
            'status' => LenderApplicationStatus::Disbursed,
            'submitted_at' => now()->subDays(10),
            'sanctioned_amount' => 650000,
            'sanctioned_at' => now()->subDays(5),
            'disbursed_amount' => 650000,
            'disbursed_at' => now()->subDay(),
        ]);
    }
}
