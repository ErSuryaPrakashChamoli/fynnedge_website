<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who did something: the customer (portal/journey), FynnEdge staff, or the
 * system itself.
 */
enum ActorType: string implements HasLabel
{
    case Customer = 'customer';
    case Staff = 'staff';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => __('Customer'),
            self::Staff => __('FynnEdge team'),
            self::System => __('System'),
        };
    }
}
