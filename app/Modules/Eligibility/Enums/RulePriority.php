<?php

namespace App\Modules\Eligibility\Enums;

use Filament\Support\Contracts\HasLabel;

enum RulePriority: string implements HasLabel
{
    case Mandatory = 'mandatory';
    case Preferred = 'preferred';
    case Warning = 'warning';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mandatory => 'Mandatory — fails the lender if not met',
            self::Preferred => 'Preferred — affects ranking only',
            self::Warning => 'Warning — surfaces a caveat, never fails',
        };
    }

    /**
     * Whether failing this rule should fail the lender outright.
     */
    public function blocksEligibility(): bool
    {
        return $this === self::Mandatory;
    }
}
