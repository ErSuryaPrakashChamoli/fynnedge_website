<?php

namespace App\Modules\MobileOtp\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The challenge's own lifecycle — deliberately separate from SmsSendStatus. An
 * SMS the provider accepted is still only Pending here; only a correct code
 * entered by the visitor ever makes a challenge Verified.
 *
 *   pending ──correct code──▶ verified
 *      │──wrong code × max──▶ exhausted
 *      │──newer request────▶ superseded
 *      │──SMS rejected─────▶ failed
 *      └──expires_at passes─▶ (expired — derived, never stored)
 *
 * Expired is never written to the column: it is a pending challenge whose
 * expires_at has passed, so storing it would need a scheduler to keep the two
 * in agreement. MobileOtpChallenge::effectiveStatus() derives it instead.
 */
enum MobileOtpStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Superseded = 'superseded';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Expired => 'Expired',
            self::Exhausted => 'Attempts exhausted',
            self::Superseded => 'Superseded',
            self::Failed => 'SMS failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Pending => 'warning',
            self::Failed, self::Exhausted => 'danger',
            self::Expired, self::Superseded => 'gray',
        };
    }
}
