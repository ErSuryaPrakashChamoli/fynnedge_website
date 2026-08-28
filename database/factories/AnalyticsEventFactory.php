<?php

namespace Database\Factories;

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsEvent>
 */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'event_key' => AnalyticsEventKey::JourneyStarted,
            'journey_session_id' => null,
            'loan_product_id' => null,
            'lender_product_id' => null,
            'properties' => null,
        ];
    }
}
