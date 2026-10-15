<?php

namespace App\Modules\MobileOtp\Exceptions;

use RuntimeException;

/**
 * A code request refused before anything was generated or sent. Each caller
 * turns this into its own answer (a 429 for the API, a field error for the
 * forms) — the action itself does not know which field the visitor is looking at.
 */
class MobileOtpThrottled extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfterSeconds, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function cooldown(int $retryAfterSeconds): self
    {
        $seconds = max(1, $retryAfterSeconds);

        return new self(__('Please wait :seconds seconds before requesting another code.', ['seconds' => $seconds]), $seconds, 'resend_cooldown');
    }

    public static function tooManyForNumber(int $retryAfterSeconds): self
    {
        return new self(__('Too many OTP requests for this number. Please try again in a while.'), max(1, $retryAfterSeconds), 'too_many_requests');
    }

    public static function tooManyFromAddress(int $retryAfterSeconds): self
    {
        return new self(__('Too many OTP requests. Please try again in a while.'), max(1, $retryAfterSeconds), 'too_many_requests');
    }
}
