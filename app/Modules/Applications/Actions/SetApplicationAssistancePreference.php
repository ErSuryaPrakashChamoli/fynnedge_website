<?php

namespace App\Modules\Applications\Actions;

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Services\AnalyticsEventDispatcher;
use App\Modules\Applications\Enums\AssistancePreference;
use App\Modules\Applications\Models\Application;

class SetApplicationAssistancePreference
{
    public function __construct(private readonly AnalyticsEventDispatcher $analytics) {}

    /**
     * Records whether the customer wants to upload documents themselves or have a
     * FynnEdge loan expert take over. Only tracks the expert-assisted analytics
     * event the first time it's chosen, so revisiting this screen doesn't
     * duplicate the funnel milestone.
     */
    public function handle(Application $application, AssistancePreference $preference): Application
    {
        $alreadyRequestedExpert = $application->assistance_preference === AssistancePreference::ExpertAssisted;

        $application->update([
            'assistance_preference' => $preference,
            'assistance_requested_at' => $preference === AssistancePreference::ExpertAssisted ? now() : null,
        ]);

        if ($preference === AssistancePreference::ExpertAssisted && ! $alreadyRequestedExpert) {
            $this->analytics->track(
                AnalyticsEventKey::LoanExpertRequested,
                session: $application->journeySession,
                lenderProduct: $application->lenderProduct,
            );
        }

        return $application;
    }
}
