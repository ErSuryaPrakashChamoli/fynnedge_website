<?php

namespace Database\Factories;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditCheck;
use App\Modules\CreditBureau\Models\CreditConsent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditCheck>
 */
class CreditCheckFactory extends Factory
{
    protected $model = CreditCheck::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'credit_consent_id' => CreditConsent::factory(),
            'provider' => 'none',
            'reference' => null,
            'status' => CreditCheckStatus::NotRequested,
            'score' => null,
            'raw_response' => null,
            'requested_at' => null,
            'completed_at' => null,
        ];
    }
}
