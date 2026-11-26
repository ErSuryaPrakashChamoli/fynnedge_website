<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdvisorReviewStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Shortlisted = 'shortlisted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting advisor review'),
            self::Shortlisted => __('Shortlisted by advisor'),
            self::Rejected => __('Not suitable (advisor)'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Shortlisted => 'success',
            self::Rejected => 'danger',
        };
    }
}
