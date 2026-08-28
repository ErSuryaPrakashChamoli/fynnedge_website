<?php

namespace App\Modules\CreditBureau\Providers;

use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;

/**
 * The default binding until a real bureau (CIBIL/Experian/etc.) is contracted and
 * its credentials configured. Makes no network call and never fabricates a score —
 * it exists so the rest of the application has something to depend on that behaves
 * honestly today: every check comes back "failed, not configured".
 */
class NullCreditBureauProvider implements CreditBureauProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function check(CreditConsent $consent): CreditCheckResult
    {
        return new CreditCheckResult(
            status: CreditCheckStatus::Failed,
            failureReason: 'No credit bureau provider is configured yet.',
        );
    }
}
