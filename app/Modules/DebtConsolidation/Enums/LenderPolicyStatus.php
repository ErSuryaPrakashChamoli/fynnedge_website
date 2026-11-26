<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LenderPolicyStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Retired => 'Retired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Retired => 'warning',
        };
    }
}
