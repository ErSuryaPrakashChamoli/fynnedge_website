<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Result of comparing a case with one lender's debt consolidation policy. A
 * "potential match" only means the case fits the lender's published rules as
 * recorded — it is never an approval or an offer.
 */
enum LenderMatchStatus: string implements HasColor, HasLabel
{
    case PotentialMatch = 'potential_match';
    case NeedsInformation = 'needs_information';
    case NotMatched = 'not_matched';

    public function getLabel(): string
    {
        return match ($this) {
            self::PotentialMatch => __('Potential match'),
            self::NeedsInformation => __('More information needed'),
            self::NotMatched => __('Does not match'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PotentialMatch => 'success',
            self::NeedsInformation => 'warning',
            self::NotMatched => 'gray',
        };
    }
}
