<?php

namespace App\Modules\CreditBureau\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A safe category for a CRIF attempt that did not end in a stored report. This
 * is what gets stored and shown instead of the provider's raw message or an
 * exception text, which may carry customer data.
 *
 * isUncertain() is the billing question: could Surepass have processed (and
 * charged for) the call? An uncertain attempt goes to reconciliation and is
 * never retried automatically. isRetryable() says whether repeating the same
 * request may succeed without a new charge for the one that failed.
 */
enum CrifErrorCode: string implements HasLabel
{
    case NotConfigured = 'not_configured';
    case ConsentInvalid = 'consent_invalid';
    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case AuthenticationFailed = 'authentication_failed';
    case InsufficientBalance = 'insufficient_balance';
    case RateLimited = 'rate_limited';
    case InvalidRequest = 'invalid_request';
    case NoRecordFound = 'no_record_found';
    case ProviderRejected = 'provider_rejected';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderError = 'provider_error';
    case MalformedResponse = 'malformed_response';
    case MissingReportPayload = 'missing_report_payload';
    case PersistenceFailed = 'persistence_failed';
    case QueueStalled = 'queue_stalled';
    case ProcessingStalled = 'processing_stalled';
    case UnexpectedError = 'unexpected_error';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotConfigured => 'Integration not configured',
            self::ConsentInvalid => 'Consent no longer valid',
            self::ConnectionFailed => 'Could not reach Surepass',
            self::Timeout => 'Surepass timed out',
            self::AuthenticationFailed => 'Surepass rejected the API token',
            self::InsufficientBalance => 'Insufficient Surepass balance',
            self::RateLimited => 'Rate limited by Surepass',
            self::InvalidRequest => 'Request rejected as invalid',
            self::NoRecordFound => 'No CRIF record found',
            self::ProviderRejected => 'Rejected by Surepass',
            self::ProviderUnavailable => 'Surepass unavailable',
            self::ProviderError => 'Surepass server error',
            self::MalformedResponse => 'Response was not valid JSON',
            self::MissingReportPayload => 'Success reported but no report returned',
            self::PersistenceFailed => 'Report could not be saved',
            self::QueueStalled => 'Request never started',
            self::ProcessingStalled => 'Request stopped while in progress',
            self::UnexpectedError => 'Unexpected error',
        };
    }

    /**
     * The provider may have processed the call: never repeat it blindly.
     */
    public function isUncertain(): bool
    {
        return in_array($this, [
            self::Timeout,
            self::ProviderError,
            self::MalformedResponse,
            self::MissingReportPayload,
            self::PersistenceFailed,
            self::ProcessingStalled,
            self::UnexpectedError,
        ], true);
    }

    /**
     * The call definitely did not produce a report, and sending the same
     * request again later may succeed (after fixing the token, topping up the
     * balance, or waiting out an outage).
     */
    public function isRetryable(): bool
    {
        return in_array($this, [
            self::NotConfigured,
            self::ConnectionFailed,
            self::AuthenticationFailed,
            self::InsufficientBalance,
            self::RateLimited,
            self::ProviderUnavailable,
            self::QueueStalled,
        ], true);
    }

    /**
     * Shown to employees. Never contains provider text or customer data.
     */
    public function safeMessage(): string
    {
        return match ($this) {
            self::NotConfigured => 'The Surepass integration is not configured or is switched off. An administrator must set it up before retrying.',
            self::ConsentInvalid => 'The customer\'s consent was withdrawn or expired before the request was sent, so nothing was sent. Record fresh consent and request again.',
            self::ConnectionFailed => 'Surepass could not be reached, so nothing was sent. You can retry.',
            self::Timeout => 'Surepass did not answer in time. It may still have processed the request, so this needs reconciliation before another attempt.',
            self::AuthenticationFailed => 'Surepass rejected the API token. An administrator must check the configured token before retrying.',
            self::InsufficientBalance => 'The Surepass account balance is too low. Top up the balance, then retry.',
            self::RateLimited => 'Surepass is limiting requests right now. Retry in a few minutes.',
            self::InvalidRequest => 'Surepass rejected the customer details as invalid. Check the name, mobile number and PAN.',
            self::NoRecordFound => 'CRIF has no credit record matching these details.',
            self::ProviderRejected => 'Surepass declined the request. Check the customer details before trying again.',
            self::ProviderUnavailable => 'Surepass is temporarily unavailable. You can retry later.',
            self::ProviderError => 'Surepass returned a server error. It may have processed the request, so this needs reconciliation.',
            self::MalformedResponse => 'Surepass answered with an unreadable response. It may have processed the request, so this needs reconciliation.',
            self::MissingReportPayload => 'Surepass reported success but sent no report. This needs reconciliation before another attempt.',
            self::PersistenceFailed => 'The report was received but could not be saved. This needs reconciliation before another attempt.',
            self::QueueStalled => 'The request was never sent to Surepass. You can retry.',
            self::ProcessingStalled => 'The request stopped while waiting for Surepass. It may have been processed, so this needs reconciliation.',
            self::UnexpectedError => 'An unexpected error occurred while fetching the report. This needs reconciliation.',
        };
    }
}
