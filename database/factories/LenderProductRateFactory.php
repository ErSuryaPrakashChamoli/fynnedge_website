<?php

namespace Database\Factories;

use App\Enums\RateStatus;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LenderProductRate>
 */
class LenderProductRateFactory extends Factory
{
    protected $model = LenderProductRate::class;

    public function definition(): array
    {
        return [
            'lender_product_id' => LenderProduct::factory(),
            'series_id' => (string) Str::uuid(),
            'version' => 1,
            'rate_min' => 10.50,
            'rate_max' => 18.00,
            'rate_unit' => RateUnit::PerAnnum,
            'repayment_method' => RepaymentMethod::ReducingBalance,
            'source_url' => 'https://www.example-lender.in/personal-loan/interest-rates',
            'source_notes' => 'Interest rate: 10.50% to 18.00% p.a.',
            'last_verified_at' => now()->toDateString(),
            'verification_status' => RateVerificationStatus::Verified,
            'status' => RateStatus::Draft,
        ];
    }

    /**
     * Live on the site: published, verified and inside its effective window.
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => RateStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn () => [
            'status' => RateStatus::PendingApproval,
            'submitted_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'verification_status' => RateVerificationStatus::Unverified,
            'source_url' => null,
            'source_notes' => null,
            'last_verified_at' => null,
        ]);
    }

    /**
     * A promotional rate that is open now and closes in a week.
     */
    public function withActivePromotion(float $rate = 9.49): static
    {
        return $this->state(fn () => [
            'promo_rate' => $rate,
            'promo_label' => 'Festive offer',
            'promo_starts_at' => now()->subDay(),
            'promo_ends_at' => now()->addWeek(),
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn () => [
            'rate_min' => 3.50,
            'rate_max' => 3.75,
            'rate_unit' => RateUnit::PerMonth,
            'repayment_method' => RepaymentMethod::RevolvingCredit,
        ]);
    }
}
