<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Each consent is purpose-specific and recorded with the exact wording the
 * customer saw (DebtConsents::text()).
 */
enum ConsentPurpose: string implements HasLabel
{
    /** FynnEdge may store and assess the information to advise on consolidation. */
    case Assessment = 'assessment';

    /** FynnEdge may call, SMS, WhatsApp or email about this case. */
    case Contact = 'contact';

    /** FynnEdge may share the case with ONE named lender to apply on the customer's behalf. */
    case LenderSharing = 'lender_sharing';

    /** A future external analysis service (e.g. FynnAI) may read the validated case data. */
    case ExternalAnalysis = 'external_analysis';

    public function getLabel(): string
    {
        return match ($this) {
            self::Assessment => __('Assessment of my debts'),
            self::Contact => __('Contact about this case'),
            self::LenderSharing => __('Share with a named lender'),
            self::ExternalAnalysis => __('External analysis service'),
        };
    }
}
