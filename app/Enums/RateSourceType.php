<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a rate's figures were read. Official = the lender's own website or
 * document (or a co-branded card's issuer). Third-party = a comparison or
 * marketplace site (Paisabazaar, IndiaLends…), used only when the lender's own
 * site could not be read. A third-party rate is shown labelled with its
 * source, never as "verified", never sets a product's "from X%" headline, and
 * stays in Needs attention until it is confirmed with the lender.
 */
enum RateSourceType: string implements HasColor, HasLabel
{
    case Official = 'official';
    case ThirdParty = 'third_party';

    public function getLabel(): string
    {
        return match ($this) {
            self::Official => 'Lender\'s own source',
            self::ThirdParty => 'Third-party (comparison site)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Official => 'success',
            self::ThirdParty => 'warning',
        };
    }
}
