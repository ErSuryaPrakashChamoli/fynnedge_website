<?php

namespace App\Modules\Experian\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an admin established about an uncertain Experian attempt from the
 * Surepass dashboard or support. Surepass offers no "status of my earlier
 * call" API, so reconciliation is a recorded human decision, never a retry.
 */
enum ExperianReconciliationResolution: string implements HasLabel
{
    /** Surepass confirms it never processed the call: nothing was charged, retrying is safe. */
    case NotProcessed = 'not_processed';

    /** Surepass processed (and charged for) the call but no result reached us: another pull is a new charge. */
    case ProcessedCharged = 'processed_charged';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotProcessed => 'Not processed by Surepass (no charge) — allow a retry',
            self::ProcessedCharged => 'Processed and charged by Surepass, but no result received',
        };
    }
}
