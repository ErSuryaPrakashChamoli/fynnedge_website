<?php

namespace Database\Factories;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    protected $model = LoanProduct::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(LoanCategory::cases());

        return [
            'public_id' => Str::uuid(),
            'name' => $category->getLabel(),
            'slug' => Str::slug($category->value).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'category' => $category,
            'summary' => $this->faker->sentence(12),
            'body' => collect($this->faker->paragraphs(3))->map(fn ($p) => "<p>{$p}</p>")->implode(''),
            'features' => $this->faker->sentences(4),
            'eligibility_points' => $this->faker->sentences(3),
            'documents_required' => ['PAN Card', 'Address Proof', 'Bank Statements (last 3 months)'],
            'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Upload documents', 'Get sanctioned'],
            'calculator_key' => null,
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Realistic EMI calculator limits, matching what's actually seeded in
     * production for each category — so a test exercising the calculator
     * isn't inventing its own numbers that could drift from real config.
     */
    public function withCalculatorLimits(): static
    {
        return $this->state(function (array $attributes) {
            $category = $attributes['category'] ?? LoanCategory::PersonalLoan;

            return match ($category) {
                LoanCategory::PersonalLoan => [
                    'min_amount' => 25_000, 'max_amount' => 5_000_000, 'default_amount' => 500_000,
                    'min_tenure_months' => 12, 'max_tenure_months' => 84, 'default_tenure_months' => 36,
                    'min_interest_rate' => 10.49, 'max_interest_rate' => 24.00, 'default_interest_rate' => 10.49,
                    'interest_rate_note' => null,
                ],
                LoanCategory::HomeLoan => [
                    'min_amount' => 500_000, 'max_amount' => 100_000_000, 'default_amount' => 4_000_000,
                    'min_tenure_months' => 60, 'max_tenure_months' => 360, 'default_tenure_months' => 240,
                    'min_interest_rate' => 7.00, 'max_interest_rate' => 10.50, 'default_interest_rate' => 7.00,
                    'interest_rate_note' => null,
                ],
                LoanCategory::CarLoan => [
                    'min_amount' => 100_000, 'max_amount' => 10_000_000, 'default_amount' => 800_000,
                    'min_tenure_months' => 12, 'max_tenure_months' => 96, 'default_tenure_months' => 60,
                    'min_interest_rate' => 9.10, 'max_interest_rate' => 15.00, 'default_interest_rate' => 9.10,
                    'interest_rate_note' => null,
                ],
                LoanCategory::LoanAgainstProperty => [
                    'min_amount' => 500_000, 'max_amount' => 75_000_000, 'default_amount' => 3_000_000,
                    'min_tenure_months' => 60, 'max_tenure_months' => 180, 'default_tenure_months' => 120,
                    'min_interest_rate' => 9.50, 'max_interest_rate' => 14.00, 'default_interest_rate' => 9.50,
                    'interest_rate_note' => null,
                ],
                LoanCategory::BusinessLoan => [
                    'min_amount' => 100_000, 'max_amount' => 20_000_000, 'default_amount' => 1_000_000,
                    'min_tenure_months' => 12, 'max_tenure_months' => 84, 'default_tenure_months' => 60,
                    'min_interest_rate' => 9.60, 'max_interest_rate' => 24.00, 'default_interest_rate' => 9.60,
                    'interest_rate_note' => null,
                ],
                LoanCategory::CreditCard => [],
            };
        });
    }
}
