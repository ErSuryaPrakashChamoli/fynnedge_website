<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who the debt consolidation case is for. Individuals are assessed on FOIR
 * (share of income going to debt), businesses on debt-service coverage.
 */
enum ApplicantType: string implements HasLabel
{
    case Individual = 'individual';
    case Business = 'business';

    public function getLabel(): string
    {
        return match ($this) {
            self::Individual => __('Individual'),
            self::Business => __('Business'),
        };
    }
}
