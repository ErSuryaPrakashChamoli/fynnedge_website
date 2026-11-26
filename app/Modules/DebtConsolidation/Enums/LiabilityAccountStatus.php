<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The repayment standing of an existing liability as declared by the customer
 * or verified by staff. Some standings mean the liability cannot currently be
 * consolidated through a new loan at all.
 */
enum LiabilityAccountStatus: string implements HasLabel
{
    case Regular = 'regular';
    case Overdue = 'overdue';
    case NonPerforming = 'non_performing';
    case Restructured = 'restructured';
    case Settled = 'settled';
    case WrittenOff = 'written_off';
    case UnderDispute = 'under_dispute';

    public function getLabel(): string
    {
        return match ($this) {
            self::Regular => __('Regular — paid on time'),
            self::Overdue => __('Overdue'),
            self::NonPerforming => __('Non-performing (90+ days overdue)'),
            self::Restructured => __('Restructured'),
            self::Settled => __('Settled with the lender'),
            self::WrittenOff => __('Written off'),
            self::UnderDispute => __('Under dispute'),
        };
    }

    /**
     * Standings no lender takes over through a consolidation loan; the case
     * needs an advisor, not a calculation.
     */
    public function blocksConsolidation(): bool
    {
        return in_array($this, [self::NonPerforming, self::Settled, self::WrittenOff, self::UnderDispute], true);
    }
}
