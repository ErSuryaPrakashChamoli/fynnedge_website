<?php

namespace App\Modules\Eligibility\Enums;

use Filament\Support\Contracts\HasLabel;

enum EligibilityStatus: string implements HasLabel
{
    case Eligible = 'eligible';
    case NotEligible = 'not_eligible';
    case Conditional = 'conditional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Eligible => 'Eligible',
            self::NotEligible => 'Not eligible',
            self::Conditional => 'Conditional',
        };
    }
}
