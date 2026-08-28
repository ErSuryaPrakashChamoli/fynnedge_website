<?php

namespace App\Modules\Eligibility\Enums;

use Filament\Support\Contracts\HasLabel;

enum RuleLogic: string implements HasLabel
{
    case And = 'and';
    case Or = 'or';

    public function getLabel(): string
    {
        return match ($this) {
            self::And => 'All conditions must pass (AND)',
            self::Or => 'Any condition may pass (OR)',
        };
    }
}
