<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Financial suitability of consolidating — never a statement about loan
 * approval, which only a lender can give.
 */
enum SuitabilityBand: string implements HasColor, HasLabel
{
    case PotentiallySuitable = 'potentially_suitable';
    case NeedsReview = 'needs_review';
    case LowSuitability = 'low_suitability';
    case NotBeneficial = 'not_beneficial';

    public function getLabel(): string
    {
        return match ($this) {
            self::PotentiallySuitable => __('Potentially suitable'),
            self::NeedsReview => __('Borderline — needs advisor review'),
            self::LowSuitability => __('Low suitability'),
            self::NotBeneficial => __('Not financially beneficial'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PotentiallySuitable => 'success',
            self::NeedsReview => 'warning',
            self::LowSuitability, self::NotBeneficial => 'danger',
        };
    }
}
