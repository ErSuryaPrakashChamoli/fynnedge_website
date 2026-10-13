<?php

namespace App\Modules\MobileOtp\Contracts;

use App\Modules\MobileOtp\DataTransferObjects\SmsSendResult;

/**
 * Sends one OTP text. Bound in AppServiceProvider from config('mobile_otp.sms_provider'),
 * so adding a gateway is a new implementation plus a config entry — controllers,
 * actions and forms never name a provider.
 *
 * Implementations must:
 *  - never throw for a provider or network failure; return SmsSendResult::failed()/unconfirmed() instead,
 *  - never retry on their own (a retry after an ambiguous timeout can text the visitor twice),
 *  - never log, store or return the message text, the code or their credentials.
 */
interface SmsProvider
{
    /** Short machine name stored on each challenge, e.g. "areach". */
    public function name(): string;

    /**
     * @param  string  $recipient  Country code + national number, digits only (e.g. 919876543210).
     */
    public function sendOtp(string $recipient, string $message): SmsSendResult;
}
