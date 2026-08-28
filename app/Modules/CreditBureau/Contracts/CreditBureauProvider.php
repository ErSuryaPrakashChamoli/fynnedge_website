<?php

namespace App\Modules\CreditBureau\Contracts;

use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Models\CreditConsent;

/**
 * A bureau check must never run without a recorded CreditConsent — implementations
 * should treat the consent as their authorization record, not just an audit trail.
 */
interface CreditBureauProvider
{
    public function name(): string;

    public function check(CreditConsent $consent): CreditCheckResult;
}
