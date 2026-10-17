<?php

namespace App\Modules\Wallet\Enums;

use Filament\Support\Contracts\HasLabel;

enum TopUpStatus: string implements HasLabel
{
    case Created = 'created';
    case Paid = 'paid';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => __('Awaiting payment'),
            self::Paid => __('Paid'),
            self::Failed => __('Failed'),
        };
    }
}
