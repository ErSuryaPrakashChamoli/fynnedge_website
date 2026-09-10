<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Deliberately short. Delivered/opened/clicked are timestamps on the row, not
 * statuses, because they arrive out of order and a row can be several at once;
 * this enum only tracks what the app itself knows about handing the message to
 * the mailer.
 */
enum RecipientStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'warning',
        };
    }
}
