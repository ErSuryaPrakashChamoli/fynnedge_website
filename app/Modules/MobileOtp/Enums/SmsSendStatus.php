<?php

namespace App\Modules\MobileOtp\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What the SMS provider told us about the send request — never whether the
 * handset received it. There is no delivery-receipt integration, so the best
 * this can ever say is that the provider accepted the message for sending.
 */
enum SmsSendStatus: string implements HasColor, HasLabel
{
    /** Challenge stored; the provider call has not returned yet. */
    case Pending = 'pending';

    /** The provider answered with an explicit success. Not proof of delivery. */
    case Accepted = 'accepted';

    /** The request may have gone out but the answer was missing or unrecognised (e.g. a read timeout). */
    case Unconfirmed = 'unconfirmed';

    /** The provider rejected the request or it never reached them. */
    case Failed = 'failed';

    /** Recorded before an SMS gateway existed — the code was shown on screen in demo mode. */
    case NotSent = 'not_sent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Sending',
            self::Accepted => 'Accepted by provider',
            self::Unconfirmed => 'Unconfirmed',
            self::Failed => 'Failed',
            self::NotSent => 'Not sent (demo mode)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Accepted => 'info',
            self::Pending, self::Unconfirmed => 'warning',
            self::Failed => 'danger',
            self::NotSent => 'gray',
        };
    }
}
