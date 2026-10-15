<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LandingPageGroup: string implements HasLabel
{
    case ByAmount = 'by-amount';
    case ByType = 'by-type';
    case ByNeed = 'by-need';
    case ByLocation = 'by-location';

    public function getLabel(): string
    {
        return match ($this) {
            self::ByAmount => __('By Amount'),
            self::ByType => __('By Type'),
            self::ByNeed => __('By Need'),
            self::ByLocation => __('By Location'),
        };
    }
}
