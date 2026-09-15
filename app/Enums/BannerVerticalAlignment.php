<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BannerVerticalAlignment: string implements HasLabel
{
    case Top = 'top';
    case Center = 'center';
    case Bottom = 'bottom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Top => 'Top',
            self::Center => 'Middle',
            self::Bottom => 'Bottom',
        };
    }
}
