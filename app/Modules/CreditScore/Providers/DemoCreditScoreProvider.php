<?php

namespace App\Modules\CreditScore\Providers;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Models\CreditScoreCheck;

/**
 * The default binding until a real bureau (CIBIL/Experian/Equifax/CRIF) is
 * contracted and its credentials configured. Makes no network call. Unlike
 * App\Modules\CreditBureau\Providers\NullCreditBureauProvider — which exists to
 * back the loan-eligibility flow honestly with "not configured" rather than fake
 * data — this provider's whole purpose is to give the public "check your free
 * score" page something to show today, so it deliberately fabricates a score.
 * Every score is clearly labelled a demo/sample in the UI (never presented as a
 * real bureau result), and it's derived from the PAN rather than pure randomness
 * so a repeat check for the same person doesn't produce a different number.
 */
class DemoCreditScoreProvider implements CreditScoreProvider
{
    public function name(): string
    {
        return 'demo';
    }

    public function check(CreditScoreCheck $check): CreditCheckResult
    {
        $score = 650 + (crc32($check->pan_number) % 251);

        return new CreditCheckResult(
            status: CreditCheckStatus::Completed,
            score: $score,
            reference: 'demo-'.$check->public_id,
        );
    }
}
