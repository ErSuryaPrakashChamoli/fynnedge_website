<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Only Active may ever receive a campaign — see NewsletterSubscriber::scopeMailable(),
 * which is the single place that rule is expressed.
 */
enum SubscriberStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending confirmation',
            self::Active => 'Active',
            self::Unsubscribed => 'Unsubscribed',
            self::Bounced => 'Bounced',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Unsubscribed => 'gray',
            self::Bounced => 'danger',
        };
    }
}
