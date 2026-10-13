<?php

namespace App\Modules\MobileOtp\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a challenge was issued for. A code is only ever accepted for the purpose
 * it was issued under, so a code texted for a credit-score check cannot verify
 * a loan application and vice versa.
 */
enum MobileOtpPurpose: string implements HasLabel
{
    /** The homepage Quick Enquiry box (and API clients redeeming against it). */
    case LoanEnquiry = 'loan_enquiry';

    /** The loan application journey's mobile-number step. */
    case LoanApplication = 'loan_application';

    /** The public free credit-score check. */
    case CreditScoreCheck = 'credit_score_check';

    public function getLabel(): string
    {
        return match ($this) {
            self::LoanEnquiry => 'Loan enquiry',
            self::LoanApplication => 'Loan application',
            self::CreditScoreCheck => 'Credit score check',
        };
    }
}
