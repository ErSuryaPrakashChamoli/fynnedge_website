<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LoanCategory: string implements HasLabel
{
    case PersonalLoan = 'personal-loan';
    case HomeLoan = 'home-loan';
    case CarLoan = 'car-loan';
    case LoanAgainstProperty = 'loan-against-property';
    case BusinessLoan = 'business-loan';
    case CreditCard = 'credit-card';
    case GoldLoan = 'gold-loan';
    case TwoWheelerLoan = 'two-wheeler-loan';
    case TermLoan = 'term-loan';
    case TractorLoan = 'tractor-loan';
    case MudraLoan = 'mudra-loan';
    case FlexiHybridTermLoan = 'flexi-hybrid-term-loan';

    public function getLabel(): string
    {
        return match ($this) {
            self::PersonalLoan => 'Personal Loan',
            self::HomeLoan => 'Home Loan',
            self::CarLoan => 'Car Loan',
            self::LoanAgainstProperty => 'Loan Against Property',
            self::BusinessLoan => 'Business Loan',
            self::CreditCard => 'Credit Card',
            self::GoldLoan => 'Gold Loan',
            self::TwoWheelerLoan => 'Two Wheeler Loan',
            self::TermLoan => 'Term Loan',
            self::TractorLoan => 'Tractor Loan',
            self::MudraLoan => 'Mudra Loan',
            self::FlexiHybridTermLoan => 'Flexi Hybrid Term Loan',
        };
    }

    /**
     * A hybrid-structured loan repays in two stages — interest-only for an
     * initial tenure, then standard principal+interest EMI for the
     * remainder — instead of one flat EMI schedule for the whole tenure.
     * Gates the two-stage calculator (EmiCalculator::calculateHybrid()) and
     * the Flexi Hybrid product page/hero, without hardcoding this one
     * category's slug anywhere else in the calculator/routing layer.
     */
    public function isHybridRepayment(): bool
    {
        return $this === self::FlexiHybridTermLoan;
    }
}
