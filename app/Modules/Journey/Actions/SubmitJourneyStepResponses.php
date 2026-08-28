<?php

namespace App\Modules\Journey\Actions;

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Services\AnalyticsEventDispatcher;
use App\Modules\CreditBureau\Actions\RecordCreditConsent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Eligibility\Services\EligibilityEngine;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Services\JourneyStepResolver;

class SubmitJourneyStepResponses
{
    public function __construct(
        private readonly JourneyStepResolver $resolver,
        private readonly EligibilityEngine $eligibilityEngine,
        private readonly RecordCreditConsent $recordCreditConsent,
        private readonly AnalyticsEventDispatcher $analytics,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(JourneySession $session, JourneyStep $step, array $validated, ?string $ipAddress = null): JourneyStep
    {
        foreach ($validated as $key => $value) {
            JourneyResponse::query()->updateOrCreate(
                ['journey_session_id' => $session->id, 'field_key' => $key],
                ['value' => $value],
            );
        }

        $this->linkCustomer($session, $validated);
        $this->recordCreditConsentIfGiven($session, $validated, $ipAddress);

        $this->analytics->track(AnalyticsEventKey::JourneyStepCompleted, session: $session, properties: ['step_key' => $step->key]);

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

        $this->analytics->track(AnalyticsEventKey::JourneyCompleted, session: $session);

        $this->eligibilityEngine->evaluateSession($session);

        return $step;
    }

    /**
     * Identifies the customer by email — whichever step collects it — and dedupes
     * across products, so the same person applying for two loans is one customer.
     *
     * @param  array<string, mixed>  $validated
     */
    private function linkCustomer(JourneySession $session, array $validated): void
    {
        if (! array_key_exists('email', $validated) || blank($validated['email'])) {
            return;
        }

        $customer = Customer::query()->updateOrCreate(
            ['email' => $validated['email']],
            array_filter([
                'full_name' => $validated['full_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ], fn ($value) => $value !== null),
        );

        if ($session->customer_id !== $customer->id) {
            $session->update(['customer_id' => $customer->id]);
        }
    }

    /**
     * The "accepted" validation rule on credit_check_consent already guarantees the
     * value is truthy if it's present in $validated at all — its presence alone means
     * consent was given. We never perform a bureau check here, only record the consent.
     *
     * @param  array<string, mixed>  $validated
     */
    private function recordCreditConsentIfGiven(JourneySession $session, array $validated, ?string $ipAddress): void
    {
        if (! array_key_exists('credit_check_consent', $validated)) {
            return;
        }

        $this->recordCreditConsent->handle($session, $ipAddress);
    }
}
