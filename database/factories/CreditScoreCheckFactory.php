<?php

namespace Database\Factories;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditScoreCheck>
 */
class CreditScoreCheckFactory extends Factory
{
    protected $model = CreditScoreCheck::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'bureau' => $this->faker->randomElement(BureauName::cases()),
            'mobile_number' => '9'.$this->faker->numerify('#########'),
            'mobile_verified_at' => now(),
            'full_name' => $this->faker->name(),
            'date_of_birth' => $this->faker->date(max: '-18 years'),
            'pan_number' => strtoupper($this->faker->bothify('?????####?')),
            'provider' => 'demo',
            'status' => CreditCheckStatus::Completed,
            'score' => $this->faker->numberBetween(650, 900),
            'raw_response' => null,
            'consent_given_at' => now(),
            'ip_address' => $this->faker->ipv4(),
            'requested_at' => now(),
            'completed_at' => now(),
        ];
    }
}
