<?php

namespace App\Modules\Wallet\Enums;

use Filament\Support\Contracts\HasLabel;

enum WalletTransactionType: string implements HasLabel
{
    /** Money added by UPI through the payment gateway. */
    case TopUp = 'top_up';

    /** A paid credit-score refresh. */
    case Refresh = 'refresh';

    /** A 6-month or 1-year refresh plan. */
    case Plan = 'plan';

    /** A credit or debit made by staff, with a reason. */
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::TopUp => __('Wallet top-up'),
            self::Refresh => __('Credit score refresh'),
            self::Plan => __('Refresh plan'),
            self::Adjustment => __('Adjustment'),
        };
    }
}
