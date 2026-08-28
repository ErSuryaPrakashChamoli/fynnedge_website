<?php

namespace Database\Factories;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditConsent>
 */
class CreditConsentFactory extends Factory
{
    protected $model = CreditConsent::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'journey_session_id' => JourneySession::factory(),
            'purpose' => 'Personal Loan eligibility check',
            'terms_version' => 'v1',
            'ip_address' => $this->faker->ipv4(),
            'status' => ConsentStatus::Given,
            'consented_at' => now(),
        ];
    }
}
