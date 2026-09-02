<?php

namespace App\Modules\CreditBureau\Providers;

use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;

/**
 * NOT bound by default — config/services.php keeps `credit_bureau.provider` on
 * NullCreditBureauProvider so a fabricated score can never silently gate a real
 * applicant's eligibility. This class exists so local/staging environments can
 * opt in explicitly (CREDIT_BUREAU_PROVIDER=...\DemoCreditBureauProvider in .env)
 * to exercise the full consent → check → eligibility pipeline before a real
 * bureau is contracted. Same deterministic formula as
 * App\Modules\CreditScore\Providers\DemoCreditScoreProvider, so a repeat check
 * for the same PAN is stable.
 */
class DemoCreditBureauProvider implements CreditBureauProvider
{
    public function name(): string
    {
        return 'demo';
    }

    public function check(CreditConsent $consent): CreditCheckResult
    {
        if (blank($consent->pan_number)) {
            return new CreditCheckResult(
                status: CreditCheckStatus::Failed,
                failureReason: 'No PAN was recorded with this consent.',
            );
        }

        $score = 650 + (crc32($consent->pan_number) % 251);

        return new CreditCheckResult(
            status: CreditCheckStatus::Completed,
            score: $score,
            reference: 'demo-'.$consent->public_id,
            rawResponse: ['note' => 'Fabricated score for demo/testing only — not a real bureau pull.'],
        );
    }
}
