<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtFinancialProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtFinancialProfile>
 */
class DebtFinancialProfileFactory extends Factory
{
    protected $model = DebtFinancialProfile::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'employment_type' => EmploymentType::Salaried,
            'employer_name' => $this->faker->company(),
            'date_of_birth' => now()->subYears(34)->toDateString(),
            'net_monthly_income' => 100000,
            'other_monthly_income' => 0,
            'monthly_living_expenses' => 30000,
            'other_monthly_obligations' => 0,
            'declared_credit_score' => 740,
        ];
    }

    public function business(): static
    {
        return $this->state(fn (): array => [
            'employment_type' => EmploymentType::SelfEmployed,
            'net_monthly_income' => null,
            'business_name' => $this->faker->company(),
            'business_constitution' => 'proprietorship',
            'business_vintage_months' => 60,
            'annual_turnover' => 9000000,
            'average_monthly_revenue' => 750000,
            'monthly_operating_expenses' => 600000,
        ]);
    }
}
