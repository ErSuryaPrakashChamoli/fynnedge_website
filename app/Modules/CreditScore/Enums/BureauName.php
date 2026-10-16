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

    /**
     * Whether this page asks for the name as on PAN in separate first, middle
     * and last name boxes. Only CRIF's API takes first_name and last_name
     * apart (a middle name travels inside first_name); CIBIL's, Experian's and
     * Equifax's take one `name` (so does Experian when it stands in for CIBIL).
     */
    public function takesSplitName(): bool
    {
        return $this === self::Crif;
    }
}
