<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

enum AffordabilityBand: string implements HasLabel
{
    case Comfortable = 'comfortable';
    case Manageable = 'manageable';
    case Stretched = 'stretched';
    case Unaffordable = 'unaffordable';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Comfortable => __('Comfortable'),
            self::Manageable => __('Manageable'),
            self::Stretched => __('Stretched'),
            self::Unaffordable => __('Not affordable'),
            self::Unknown => __('Not enough information'),
        };
    }
}
