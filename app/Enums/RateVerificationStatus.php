<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether a rate has been checked against the lender's own published
 * schedule. Only Verified rates can be submitted or published; a verified
 * rate whose last_verified_at has aged past the re-verification window is
 * flagged "needs re-verification" in the admin (derived, not stored).
 */
enum RateVerificationStatus: string implements HasColor, HasLabel
{
    case Verified = 'verified';
    case Unverified = 'unverified';

    public function getLabel(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Unverified => 'Unverified',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Unverified => 'danger',
        };
    }
}
