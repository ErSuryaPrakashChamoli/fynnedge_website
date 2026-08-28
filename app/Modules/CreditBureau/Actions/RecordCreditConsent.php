<?php

namespace App\Modules\CreditBureau\Actions;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Journey\Models\JourneySession;

class RecordCreditConsent
{
    public function handle(JourneySession $session, ?string $ipAddress = null): CreditConsent
    {
        return CreditConsent::query()->firstOrCreate(
            ['journey_session_id' => $session->id],
            [
                'purpose' => "{$session->loanProduct->name} eligibility check",
                'terms_version' => config('services.credit_bureau.terms_version', 'v1'),
                'ip_address' => $ipAddress,
                'status' => ConsentStatus::Given,
                'consented_at' => now(),
            ],
        );
    }
}
