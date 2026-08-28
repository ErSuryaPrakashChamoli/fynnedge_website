<?php

namespace App\Modules\Journey\Actions;

use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Services\JourneyStepResolver;

class SubmitJourneyStepResponses
{
    public function __construct(private readonly JourneyStepResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(JourneySession $session, JourneyStep $step, array $validated): JourneyStep
    {
        foreach ($validated as $key => $value) {
            JourneyResponse::query()->updateOrCreate(
                ['journey_session_id' => $session->id, 'field_key' => $key],
                ['value' => $value],
            );
        }

        $responses = $session->responsesByKey();
        $next = $this->resolver->nextStep($session->journeyDefinition, $step, $responses);

        if ($next) {
            $session->update(['current_step_id' => $next->id]);

            return $next;
        }

        $session->update([
            'status' => JourneySessionStatus::Completed,
            'completed_at' => now(),
        ]);

        return $step;
    }
}
