<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LenderType: string implements HasLabel
{
    case Bank = 'bank';
    case Nbfc = 'nbfc';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::Nbfc => 'NBFC / HFC',
        };
    }
}
