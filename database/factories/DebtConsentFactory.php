<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtConsent>
 */
class DebtConsentFactory extends Factory
{
    protected $model = DebtConsent::class;

    public function definition(): array
    {
        $text = 'I agree to the assessment of my debts.';

        return [
            'debt_application_id' => DebtApplication::factory(),
            'purpose' => ConsentPurpose::Assessment,
            'consent_text' => $text,
            'text_version' => '1',
            'text_hash' => hash('sha256', $text),
            'method' => DebtConsent::METHOD_WEB_CHECKBOX,
            'granted_at' => now(),
        ];
    }
}
