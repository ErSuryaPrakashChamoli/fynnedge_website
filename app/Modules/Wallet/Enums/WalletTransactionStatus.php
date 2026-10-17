<?php

namespace App\Modules\Wallet\Enums;

use Filament\Support\Contracts\HasLabel;

enum WalletTransactionStatus: string implements HasLabel
{
    /** Reserved for a refresh that is running; counts against the balance. */
    case Held = 'held';

    case Completed = 'completed';

    /** A hold given back because the bureau gave no new result. Not charged. */
    case Released = 'released';

    public function getLabel(): string
    {
        return match ($this) {
            self::Held => __('On hold'),
            self::Completed => __('Completed'),
            self::Released => __('Refunded'),
        };
    }

    /**
     * The statuses the balance is the sum of.
     *
     * @return list<self>
     */
    public static function counted(): array
    {
        return [self::Held, self::Completed];
    }
}
