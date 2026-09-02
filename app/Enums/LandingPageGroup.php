<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LandingPageGroup: string implements HasLabel
{
    case ByAmount = 'by-amount';
    case ByType = 'by-type';
    case ByNeed = 'by-need';

    public function getLabel(): string
    {
        return match ($this) {
            self::ByAmount => 'By Amount',
            self::ByType => 'By Type',
            self::ByNeed => 'By Need',
        };
    }
}
