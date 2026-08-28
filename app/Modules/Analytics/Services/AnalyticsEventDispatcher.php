<?php

namespace App\Modules\Analytics\Services;

use App\Models\LenderProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Journey\Models\JourneySession;

class AnalyticsEventDispatcher
{
    /**
     * Records one funnel milestone. Deliberately synchronous and dependency-free —
     * this is a first-party event log, not an integration with an external analytics
     * vendor, so there's nothing to queue or batch.
     *
     * @param  array<string, mixed>  $properties
     */
    public function track(
        AnalyticsEventKey $key,
        ?JourneySession $session = null,
        ?LenderProduct $lenderProduct = null,
        array $properties = [],
    ): AnalyticsEvent {
        return AnalyticsEvent::query()->create([
            'event_key' => $key,
            'journey_session_id' => $session?->id,
            'loan_product_id' => $session?->loan_product_id ?? $lenderProduct?->loan_product_id,
            'lender_product_id' => $lenderProduct?->id,
            'properties' => $properties,
        ]);
    }
}
