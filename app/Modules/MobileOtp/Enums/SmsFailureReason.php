<?php

namespace App\Modules\MobileOtp\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A safe category for a send that was not cleanly accepted. This is what gets
 * stored and shown to admins instead of the provider's raw response, which may
 * echo the message text (and so the code) back.
 */
enum SmsFailureReason: string implements HasLabel
{
    case NotConfigured = 'not_configured';
    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case HttpError = 'http_error';
    case ProviderRejected = 'provider_rejected';
    case InvalidResponse = 'invalid_response';
    case UnrecognisedResponse = 'unrecognised_response';
    case UnexpectedError = 'unexpected_error';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotConfigured => 'Provider not configured',
            self::ConnectionFailed => 'Could not reach provider',
            self::Timeout => 'Provider timed out',
            self::HttpError => 'Provider HTTP error',
            self::ProviderRejected => 'Rejected by provider',
            self::InvalidResponse => 'Response was not JSON',
            self::UnrecognisedResponse => 'Response not recognised',
            self::UnexpectedError => 'Unexpected error',
        };
    }
}
