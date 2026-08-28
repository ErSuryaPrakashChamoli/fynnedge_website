<?php

namespace App\Modules\Applications\Actions;

use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Models\EligibilityResult;

class SelectLenderForApplication
{
    /**
     * Idempotent per (journey session, lender product) — revisiting the same
     * selection reuses the existing application instead of creating a duplicate.
     */
    public function handle(EligibilityResult $result): ?Application
    {
        if ($result->status === EligibilityStatus::NotEligible) {
            return null;
        }

        return Application::query()->firstOrCreate(
            [
                'journey_session_id' => $result->journey_session_id,
                'lender_product_id' => $result->lender_product_id,
            ],
            [
                'customer_id' => $result->journeySession->customer_id,
                'eligibility_result_id' => $result->id,
                'status' => ApplicationStatus::LenderSelected,
            ],
        );
    }
}
