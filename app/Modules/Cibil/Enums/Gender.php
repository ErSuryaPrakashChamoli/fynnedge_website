<?php

namespace App\Modules\Cibil\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The genders Surepass's CIBIL API accepts — exactly "male" and "female",
 * lower case ("Male", "other" and "transgender" are rejected as "not a valid
 * GenderEnum"; checked against the live API's input validation on 16 Oct 2026).
 */
enum Gender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}
