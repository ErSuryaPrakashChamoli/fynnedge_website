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

    public function getLabel(): string
    {
        return match ($this) {
            self::PersonalLoan => 'Personal Loan',
            self::HomeLoan => 'Home Loan',
            self::CarLoan => 'Car Loan',
            self::LoanAgainstProperty => 'Loan Against Property',
            self::BusinessLoan => 'Business Loan',
            self::CreditCard => 'Credit Card',
        };
    }
}
