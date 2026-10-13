<?php

namespace App\Modules\MobileOtp\Providers;

use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\DataTransferObjects\SmsSendResult;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use Illuminate\Support\Facades\Log;

/**
 * Local development only — the SMS counterpart of MAIL_MAILER=log. The text
 * (code included) is written to the log so a developer without Areach
 * credentials can still walk the OTP forms end to end.
 *
 * Refuses to run outside APP_ENV=local/testing: anywhere else (production,
 * staging) it returns a Failed result without writing the message anywhere, so
 * a misconfigured MOBILE_OTP_SMS_PROVIDER fails loudly instead of quietly
 * putting live codes into shared log files.
 */
class LogSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function sendOtp(string $recipient, string $message): SmsSendResult
    {
        if (! app()->environment('local', 'testing')) {
            Log::critical('Mobile OTP: the "log" SMS provider is only allowed when APP_ENV is local; nothing was sent. Set MOBILE_OTP_SMS_PROVIDER=areach.');

            return SmsSendResult::failed(SmsFailureReason::NotConfigured);
        }

        Log::info("Mobile OTP (log provider, not sent) to {$recipient}: {$message}");

        return SmsSendResult::accepted('log-'.bin2hex(random_bytes(6)));
    }
}
