<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the quoted rate is applied. A flat rate and a reducing-balance rate of
 * the same number cost very different amounts, so the site names the method
 * next to any rate that is not an ordinary reducing-balance EMI.
 */
enum RepaymentMethod: string implements HasLabel
{
    case ReducingBalance = 'reducing_balance';
    case Flat = 'flat';
    case RevolvingCredit = 'revolving_credit';
    case Overdraft = 'overdraft';
    case HybridInterestOnlyThenEmi = 'hybrid_interest_only_then_emi';

    public function getLabel(): string
    {
        return match ($this) {
            self::ReducingBalance => 'Reducing balance (EMI)',
            self::Flat => 'Flat rate',
            self::RevolvingCredit => 'Revolving credit (card)',
            self::Overdraft => 'Overdraft / dropline',
            self::HybridInterestOnlyThenEmi => 'Interest-only, then EMI (hybrid)',
        };
    }
}
