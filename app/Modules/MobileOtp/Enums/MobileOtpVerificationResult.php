<?php

namespace App\Modules\MobileOtp\Enums;

/**
 * The outcome of one verification attempt. Only Verified means the visitor
 * proved they hold the number; every other case is a refusal, and each carries
 * its own message so a caller can tell the visitor what to do next.
 */
enum MobileOtpVerificationResult: string
{
    case Verified = 'verified';
    case Incorrect = 'invalid_otp';
    case Expired = 'otp_expired';
    case AlreadyVerified = 'otp_already_used';
    case Superseded = 'otp_superseded';
    case AttemptsExhausted = 'too_many_attempts';
    case NotSent = 'otp_not_sent';

    /** No challenge matched the id, number and purpose given — answered exactly like a wrong code. */
    case NotFound = 'invalid_otp_challenge';

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    /**
     * The code a public client sees. NotFound deliberately reports the same
     * code as a wrong OTP: a stranger probing ids or numbers learns nothing
     * about whether a challenge exists for someone else's number.
     */
    public function errorCode(): string
    {
        return $this === self::NotFound ? self::Incorrect->value : $this->value;
    }

    public function message(): string
    {
        return match ($this) {
            self::Verified => __('Mobile number verified successfully.'),
            self::Incorrect, self::NotFound => __('That code is incorrect or has expired. You can request a new one.'),
            self::Expired => __('That code has expired. Please request a new one.'),
            self::AlreadyVerified => __('That code has already been used. Please request a new one.'),
            self::Superseded => __('A newer code has been sent. Please enter the latest code.'),
            self::AttemptsExhausted => __('Too many incorrect attempts. Please request a new code.'),
            self::NotSent => __('We could not send that code. Please request a new one.'),
        };
    }
}
