<?php

namespace Database\Factories;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('##########'),
        ];
    }

    /**
     * An activated customer-portal account: its mobile proved by OTP.
     */
    public function portalAccount(?string $mobile = null): static
    {
        return $this->state(function () use ($mobile): array {
            $mobile ??= '9'.$this->faker->unique()->numerify('#########');

            return [
                'phone' => $mobile,
                'verified_mobile' => $mobile,
                'mobile_verified_at' => now()->subDay(),
                'portal_activated_at' => now()->subDay(),
            ];
        });
    }

    /** A portal account staff have switched off. */
    public function portalDisabled(): static
    {
        return $this->state(fn (): array => ['portal_disabled_at' => now()]);
    }
}
