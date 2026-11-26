<?php

namespace Database\Factories;

use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtApplication>
 */
class DebtApplicationFactory extends Factory
{
    protected $model = DebtApplication::class;

    public function definition(): array
    {
        return [
            'applicant_type' => ApplicantType::Individual,
            'status' => DebtApplicationStatus::Draft,
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'mobile' => '9'.$this->faker->unique()->numerify('#########'),
            'mobile_verified_at' => now()->subHour(),
            'city' => 'Pune',
        ];
    }

    /**
     * A case opened from the given portal account's verified mobile.
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $customer->getKey(),
            'mobile' => $customer->verified_mobile,
            'mobile_verified_at' => now()->subHour(),
            'full_name' => $customer->full_name,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (): array => ['applicant_type' => ApplicantType::Business]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => DebtApplicationStatus::EnquiryReceived,
            'enquiry_submitted_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'journey_step' => 'submitted',
        ]);
    }

    public function enquiryOnly(): static
    {
        return $this->state(fn (): array => [
            'status' => DebtApplicationStatus::EnquiryReceived,
            'enquiry_submitted_at' => now()->subDay(),
            'declared_total_outstanding' => 800000,
            'declared_monthly_emi' => 32000,
        ]);
    }
}
