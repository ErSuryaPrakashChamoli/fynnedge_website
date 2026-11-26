<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnquiryType: string implements HasLabel
{
    case Contact = 'contact';
    case QuickEnquiry = 'quick_enquiry';
    case LoanEnquiry = 'loan_enquiry';

    /** The Debt Consolidation enquiry form; also opens a debt_applications case. */
    case DebtConsolidation = 'debt_consolidation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contact => 'Contact Form',
            self::QuickEnquiry => 'Quick Enquiry',
            self::LoanEnquiry => 'Loan Enquiry',
            self::DebtConsolidation => 'Debt Consolidation',
        };
    }
}
