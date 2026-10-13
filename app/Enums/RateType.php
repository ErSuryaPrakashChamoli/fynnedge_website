<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RateType: string implements HasLabel
{
    case Fixed = 'fixed';
    case Floating = 'floating';
    case FixedOrFloating = 'fixed_or_floating';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Floating => 'Floating',
            self::FixedOrFloating => 'Fixed or floating',
        };
    }
}
