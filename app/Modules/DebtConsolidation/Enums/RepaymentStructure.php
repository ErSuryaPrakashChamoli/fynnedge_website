<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an existing liability is being repaid — it decides how the engine
 * projects the remaining cost of keeping it.
 */
enum RepaymentStructure: string implements HasLabel
{
    /** Fixed EMI covering interest and principal (personal, vehicle, home, term loans). */
    case Amortising = 'amortising';

    /** Variable monthly payment against a revolving balance (credit card outstanding). */
    case Revolving = 'revolving';

    /** Interest each month, principal at the end (gold loan bullet, cash credit, overdraft, invoice finance). */
    case InterestOnly = 'interest_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::Amortising => __('Fixed EMI'),
            self::Revolving => __('Revolving (variable payment)'),
            self::InterestOnly => __('Interest only, principal at the end'),
        };
    }
}
