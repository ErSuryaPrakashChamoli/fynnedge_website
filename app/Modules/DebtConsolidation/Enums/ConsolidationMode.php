<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Whether a liability is closed in full, paid down in part, or left as it is
 * by the proposed consolidation.
 */
enum ConsolidationMode: string implements HasLabel
{
    case Full = 'full';
    case Partial = 'partial';
    case Exclude = 'exclude';

    public function getLabel(): string
    {
        return match ($this) {
            self::Full => __('Close in full'),
            self::Partial => __('Pay down part'),
            self::Exclude => __('Keep as it is'),
        };
    }

    public function isIncluded(): bool
    {
        return $this !== self::Exclude;
    }
}
