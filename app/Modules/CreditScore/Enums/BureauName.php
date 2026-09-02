<?php

namespace App\Modules\CreditScore\Enums;

use Filament\Support\Contracts\HasLabel;

enum BureauName: string implements HasLabel
{
    case Cibil = 'cibil';
    case Experian = 'experian';
    case Equifax = 'equifax';
    case Crif = 'crif';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cibil => 'CIBIL',
            self::Experian => 'Experian',
            self::Equifax => 'Equifax',
            self::Crif => 'CRIF',
        };
    }
}
