<?php

namespace App\Modules\Journey\Enums;

use Filament\Support\Contracts\HasLabel;

enum JourneySessionStatus: string implements HasLabel
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Abandoned => 'Abandoned',
        };
    }
}
