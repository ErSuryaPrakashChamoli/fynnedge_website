<?php

namespace App\Modules\CreditScore\Actions;

use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Hash;

class VerifyMobileOtp
{
    private const MAX_ATTEMPTS = 5;

    public function handle(MobileOtpChallenge $challenge, string $code): bool
    {
        if ($challenge->isVerified() || $challenge->isExpired() || $challenge->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->otp_hash)) {
            return false;
        }

        $challenge->forceFill(['verified_at' => now()])->save();

        return true;
    }
}
