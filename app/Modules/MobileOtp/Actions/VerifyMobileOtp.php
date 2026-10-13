<?php

namespace App\Modules\MobileOtp\Actions;

use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Judges one code against one challenge. The challenge is re-read under a row
 * lock inside a transaction, so simultaneous attempts on the same challenge are
 * serialised: the attempt counter can't be raced past max_attempts, and a code
 * can't verify twice. No external call happens inside the lock.
 *
 * The caller must already have matched the challenge to the submitted number
 * and purpose (MobileOtpChallenge::findForVerification()); this class only
 * decides whether the code is right.
 */
class VerifyMobileOtp
{
    public function handle(MobileOtpChallenge $challenge, string $code): MobileOtpVerificationResult
    {
        return DB::transaction(function () use ($challenge, $code): MobileOtpVerificationResult {
            $locked = MobileOtpChallenge::query()->whereKey($challenge->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return MobileOtpVerificationResult::NotFound;
            }

            $result = $this->judge($locked, $code);

            // Hand the caller the state the database now holds, not the stale copy it passed in.
            $challenge->setRawAttributes($locked->getAttributes(), true);

            return $result;
        });
    }

    private function judge(MobileOtpChallenge $challenge, string $code): MobileOtpVerificationResult
    {
        $refusal = match ($challenge->status) {
            MobileOtpStatus::Verified => MobileOtpVerificationResult::AlreadyVerified,
            MobileOtpStatus::Superseded => MobileOtpVerificationResult::Superseded,
            MobileOtpStatus::Exhausted => MobileOtpVerificationResult::AttemptsExhausted,
            MobileOtpStatus::Failed => MobileOtpVerificationResult::NotSent,
            default => null,
        };

        if ($refusal) {
            return $refusal;
        }

        if ($challenge->isExpired()) {
            return MobileOtpVerificationResult::Expired;
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            $challenge->forceFill(['status' => MobileOtpStatus::Exhausted])->save();

            return MobileOtpVerificationResult::AttemptsExhausted;
        }

        $challenge->attempts++;

        if (! Hash::check($code, $challenge->otp_hash)) {
            $exhausted = $challenge->attempts >= $challenge->max_attempts;

            $challenge->forceFill(['status' => $exhausted ? MobileOtpStatus::Exhausted : MobileOtpStatus::Pending])->save();

            return $exhausted ? MobileOtpVerificationResult::AttemptsExhausted : MobileOtpVerificationResult::Incorrect;
        }

        $challenge->forceFill(['status' => MobileOtpStatus::Verified, 'verified_at' => now()])->save();

        return MobileOtpVerificationResult::Verified;
    }
}
