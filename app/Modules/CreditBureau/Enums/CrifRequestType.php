<?php

namespace App\Modules\CreditBureau\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CrifRequestType: string implements HasColor, HasLabel
{
    /** An ordinary request: reuses a report still inside the reuse period instead of paying again. */
    case Standard = 'standard';

    /** A repeat of a failed attempt the provider is known not to have processed. */
    case Retry = 'retry';

    /** An authorised override that pays for a new report even though a reusable one exists. */
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
