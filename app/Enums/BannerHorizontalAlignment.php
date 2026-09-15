<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BannerHorizontalAlignment: string implements HasLabel
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';

    public function getLabel(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Center => 'Centre',
            self::Right => 'Right',
        };
    }
}
