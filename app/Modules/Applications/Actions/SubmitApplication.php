<?php

namespace App\Modules\Applications\Actions;

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Services\AnalyticsEventDispatcher;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;

class SubmitApplication
{
    public function __construct(private readonly AnalyticsEventDispatcher $analytics) {}

    /**
     * @return array<int, string> validation errors — empty means it was submitted
     */
    public function handle(Application $application): array
    {
        if (! $application->hasAllRequiredDocuments()) {
            return ['Please upload every required document before submitting.'];
        }

        $application->update([
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->analytics->track(
            AnalyticsEventKey::ApplicationSubmitted,
            session: $application->journeySession,
            lenderProduct: $application->lenderProduct,
        );

        return [];
    }
}
