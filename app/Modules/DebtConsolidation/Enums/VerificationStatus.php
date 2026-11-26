<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether staff have checked a customer-declared figure (liability,
 * collateral, foreclosure quotation) against a document.
 */
enum VerificationStatus: string implements HasColor, HasLabel
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Discrepancy = 'discrepancy';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unverified => __('Not verified'),
            self::Verified => __('Verified'),
            self::Discrepancy => __('Does not match documents'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unverified => 'gray',
            self::Verified => 'success',
            self::Discrepancy => 'danger',
        };
    }
}
