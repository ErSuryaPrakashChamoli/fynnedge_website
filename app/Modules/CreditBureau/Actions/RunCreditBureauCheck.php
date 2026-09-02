<?php

namespace App\Modules\CreditBureau\Actions;

use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditCheck;
use App\Modules\CreditBureau\Models\CreditConsent;

class RunCreditBureauCheck
{
    public function __construct(private readonly CreditBureauProvider $provider) {}

    public function handle(CreditConsent $consent): CreditCheck
    {
        $check = CreditCheck::query()->create([
            'credit_consent_id' => $consent->id,
            'provider' => $this->provider->name(),
            'status' => CreditCheckStatus::Pending,
            'requested_at' => now(),
        ]);

        $result = $this->provider->check($consent);

        $check->update([
            'status' => $result->status,
            'score' => $result->score,
            'reference' => $result->reference,
            'raw_response' => $result->rawResponse,
            'completed_at' => now(),
        ]);

        return $check->refresh();
    }
}
