<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel
{
    case Salaried = 'salaried';
    case SelfEmployed = 'self-employed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Salaried => 'Salaried',
            self::SelfEmployed => 'Self-employed',
        };
    }
}
