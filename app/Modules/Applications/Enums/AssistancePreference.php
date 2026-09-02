<?php

namespace App\Modules\Applications\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssistancePreference: string implements HasLabel
{
    case SelfService = 'self_service';
    case ExpertAssisted = 'expert_assisted';

    public function getLabel(): string
    {
        return match ($this) {
            self::SelfService => 'Self-service',
            self::ExpertAssisted => 'Expert-assisted',
        };
    }
}
