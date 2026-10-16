<?php

namespace App\Modules\Experian\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExperianRequestType: string implements HasColor, HasLabel
{
    /** An ordinary request: reuses a result still inside its reuse period instead of paying again. */
    case Standard = 'standard';

    /** A repeat of a failed attempt Surepass is known not to have processed. */
    case Retry = 'retry';

    /** An authorised override that pays again although a reusable result exists. */
    case Fresh = 'fresh';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Retry => 'Retry',
            self::Fresh => 'Fresh (override)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Standard => 'gray',
            self::Retry => 'info',
            self::Fresh => 'warning',
        };
    }
}
