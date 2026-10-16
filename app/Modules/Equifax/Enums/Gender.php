<?php

namespace App\Modules\Equifax\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The genders Surepass's Equifax API accepts — exactly "male" and "female",
 * lower case (anything else, including "other", is rejected at validation;
 * checked against the live API on 16 Oct 2026).
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
