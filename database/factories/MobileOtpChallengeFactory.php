<?php

namespace Database\Factories;

use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileOtpChallenge>
 */
class MobileOtpChallengeFactory extends Factory
{
    protected $model = MobileOtpChallenge::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'mobile_number' => '9'.$this->faker->numerify('#########'),
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'attempts' => 0,
            'ip_address' => $this->faker->ipv4(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }
}
