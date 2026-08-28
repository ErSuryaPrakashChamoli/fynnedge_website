<?php

namespace App\Modules\CreditBureau\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConsentStatus: string implements HasLabel
{
    case Given = 'given';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Given => 'Given',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
