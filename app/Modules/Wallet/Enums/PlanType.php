<?php

namespace App\Modules\Wallet\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Refresh plans: while one is active every bureau can be refreshed once every
 * 30 days with no per-refresh charge. Prices (before GST) come from
 * config('customer_portal.billing.plans').
 */
enum PlanType: string implements HasLabel
{
    case HalfYear = 'half_year';
    case Annual = 'annual';

    public function getLabel(): string
    {
        return match ($this) {
            self::HalfYear => __('6-month plan'),
            self::Annual => __('1-year plan'),
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::HalfYear => 6,
            self::Annual => 12,
        };
    }

    /** Price before GST, in paise. */
    public function pricePaise(): int
    {
        return (int) config('customer_portal.billing.plans.'.$this->value);
    }
}
