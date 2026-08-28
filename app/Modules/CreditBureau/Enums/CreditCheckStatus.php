<?php

namespace App\Modules\CreditBureau\Enums;

use Filament\Support\Contracts\HasLabel;

enum CreditCheckStatus: string implements HasLabel
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotRequested => 'Not requested',
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
