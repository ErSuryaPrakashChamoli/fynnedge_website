<?php

namespace App\Support\Calculators;

use Filament\Support\Contracts\HasLabel;

/**
 * The calculator pages that have no LoanProduct to hang admin-editable
 * "about this calculator" content off — see App\Models\CalculatorPage.
 * Loan-category calculators (EMI, Eligibility, Prepayment) aren't listed
 * here; they reuse LoanProduct::calculator_explanation instead.
 */
enum CalculatorPageKey: string implements HasLabel
{
    case FixedDeposit = 'fixed-deposit';
    case Sip = 'sip';
    case DailySip = 'daily-sip';
    case Gst = 'gst';

    public function getLabel(): string
    {
        return match ($this) {
            self::FixedDeposit => 'Fixed Deposit Calculator',
            self::Sip => 'SIP Calculator',
            self::DailySip => 'Daily SIP Calculator',
            self::Gst => 'GST Calculator',
        };
    }
}
