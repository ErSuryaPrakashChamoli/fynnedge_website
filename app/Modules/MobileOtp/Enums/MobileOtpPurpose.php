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

    /** The contact page form, only when the visitor fills in the optional phone number. */
    case ContactEnquiry = 'contact_enquiry';

    /**
     * Signing in to the FynnOn customer portal (/existing-customer). Issued and
     * redeemed only by the portal's own login form — never through the public
     * API, which refuses this purpose (see apiPurposes()).
     */
    case CustomerLogin = 'customer_login';

    /**
     * The purposes the public JSON API may issue and verify codes for.
     *
     * @return list<self>
     */
    public static function apiPurposes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $purpose): bool => $purpose !== self::CustomerLogin));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LoanEnquiry => 'Loan enquiry',
            self::LoanApplication => 'Loan application',
            self::CreditScoreCheck => 'Credit score check',
            self::ContactEnquiry => 'Contact form',
            self::CustomerLogin => 'Customer portal login',
        };
    }
}
