<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Services\MakerChecker;
use Illuminate\Validation\ValidationException;

/**
 * Marks a lender's foreclosure quotation as checked against the lender's
 * letter. Only someone independent of the quotation may do it: not the
 * person who recorded it, nor anyone who changed it (SOP-05 / BV-08,
 * MakerChecker::ensureIndependent, no exemption).
 */
class VerifyForeclosureQuotation
{
    /**
     * @throws ValidationException
     */
    public function handle(DebtForeclosureQuotation $quotation, int $userId): DebtForeclosureQuotation
    {
        MakerChecker::ensureIndependent($quotation, $userId, 'verification_status', 'A different person must verify this quotation: you recorded or changed it.');

        $quotation->update(['verification_status' => VerificationStatus::Verified, 'verified_by' => $userId, 'verified_at' => now()]);

        return $quotation;
    }
}
