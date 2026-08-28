<?php

namespace App\Modules\Journey\Enums;

use Filament\Support\Contracts\HasLabel;

enum JourneyDefinitionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }
}
