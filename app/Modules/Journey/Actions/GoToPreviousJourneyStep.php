<?php

namespace App\Modules\Journey\Actions;

use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Services\JourneyStepResolver;

class GoToPreviousJourneyStep
{
    public function __construct(private readonly JourneyStepResolver $resolver) {}

    public function handle(JourneySession $session): void
    {
        if (! $session->currentStep) {
            return;
        }

        $previous = $this->resolver->previousStep(
            $session->journeyDefinition,
            $session->currentStep,
            $session->responsesByKey(),
        );

        if ($previous) {
            $session->update(['current_step_id' => $previous->id]);
        }
    }
}
