<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which screens a promo bar appears on. The split is Tailwind's `sm`
 * breakpoint (640px), the same one the rest of the site's layout uses.
 */
enum PromoBarDevice: string implements HasLabel
{
    case All = 'all';
    case Mobile = 'mobile';
    case Desktop = 'desktop';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Phones, tablets and desktops',
            self::Mobile => 'Phones only',
            self::Desktop => 'Tablets and desktops only',
        };
    }
}
