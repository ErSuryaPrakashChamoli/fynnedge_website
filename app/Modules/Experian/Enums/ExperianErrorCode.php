<?php

namespace App\Modules\Experian\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A safe category for an Experian attempt that did not end in a stored result.
 * This is what is stored and shown instead of Surepass's message or an
 * exception text, which may carry customer data.
 *
 * isUncertain() is the billing question: could Surepass have processed (and
 * charged for) the call? An uncertain attempt goes to reconciliation and is
 * never retried automatically. isRetryable() says whether repeating the same
 * request may succeed without the failed one having been charged.
 */
enum ExperianErrorCode: string implements HasLabel
{
    case NotConfigured = 'not_configured';
    case ConsentInvalid = 'consent_invalid';
    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case AuthenticationFailed = 'authentication_failed';
    case ApiNotEnabled = 'api_not_enabled';
    case InsufficientBalance = 'insufficient_balance';
    case RateLimited = 'rate_limited';
    case InvalidRequest = 'invalid_request';
    case NoRecordFound = 'no_record_found';
    case ProviderRejected = 'provider_rejected';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderError = 'provider_error';
    case MalformedResponse = 'malformed_response';
    case MissingScore = 'missing_score';
    case MissingReportPayload = 'missing_report_payload';
    case MissingPdfLink = 'missing_pdf_link';
    case PdfUnavailable = 'pdf_unavailable';
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
            self::ApiNotEnabled => 'API not enabled on the Surepass account',
            self::InsufficientBalance => 'Insufficient Surepass balance',
            self::RateLimited => 'Rate limited by Surepass',
            self::InvalidRequest => 'Request rejected as invalid',
            self::NoRecordFound => 'No Experian record found',
            self::ProviderRejected => 'Rejected by Surepass',
            self::ProviderUnavailable => 'Surepass unavailable',
            self::ProviderError => 'Surepass server error',
            self::MalformedResponse => 'Response was not valid JSON',
            self::MissingScore => 'Success reported but no score returned',
            self::MissingReportPayload => 'Success reported but no report returned',
            self::MissingPdfLink => 'Success reported but no PDF link returned',
            self::PdfUnavailable => 'PDF link expired or unreadable',
            self::PersistenceFailed => 'Result could not be saved',
            self::QueueStalled => 'Request never started',
            self::ProcessingStalled => 'Request stopped while in progress',
            self::UnexpectedError => 'Unexpected error',
        };
    }

    /**
     * Surepass may have processed the call: never repeat it blindly.
     */
    public function isUncertain(): bool
    {
        return in_array($this, [
            self::Timeout,
            self::ProviderError,
            self::MalformedResponse,
            self::MissingScore,
            self::MissingReportPayload,
            self::MissingPdfLink,
            self::PersistenceFailed,
            self::ProcessingStalled,
            self::UnexpectedError,
        ], true);
    }

    /**
     * The call definitely produced nothing, and sending the same request again
     * later may succeed (after fixing the token, enabling the API, topping up the
     * balance or waiting out an outage).
     */
    public function isRetryable(): bool
    {
        return in_array($this, [
            self::NotConfigured,
            self::ConnectionFailed,
            self::AuthenticationFailed,
            self::ApiNotEnabled,
            self::InsufficientBalance,
            self::RateLimited,
            self::ProviderUnavailable,
            self::QueueStalled,
        ], true);
    }

    /**
     * Shown to employees and admins. Never contains provider text or customer data.
     */
    public function safeMessage(): string
    {
        return match ($this) {
            self::NotConfigured => 'The Experian integration is not configured or is switched off. An administrator must set it up before retrying.',
            self::ConsentInvalid => 'The customer\'s consent was withdrawn or expired before the request was sent, so nothing was sent. Record fresh consent and request again.',
            self::ConnectionFailed => 'Surepass could not be reached, so nothing was sent. Check that this server can reach kyc-api.surepass.app (firewall, DNS), then retry.',
            self::Timeout => 'Surepass did not answer in time. It may still have processed the request, so this needs reconciliation before another attempt.',
            self::AuthenticationFailed => 'Surepass rejected the API token (HTTP 401/403), so nothing was charged. Check that SUREPASS_EXPERIAN_TOKEN (or the shared SUREPASS_BEARER_TOKEN) is your production token and was copied whole, then clear the config cache and retry.',
            self::ApiNotEnabled => 'The Surepass token is valid, but your Surepass plan does not include this Experian API (HTTP 401 "not valid for this API/Scope"), so nothing was charged. Ask Surepass support to enable it. Run `php artisan experian:check-access` to see which Experian APIs your token can use.',
            self::InsufficientBalance => 'The Surepass wallet balance is too low (HTTP 402), so nothing was charged. Top up the balance in the Surepass console, then retry.',
            self::RateLimited => 'Surepass is limiting requests right now. Retry in a few minutes.',
            self::InvalidRequest => 'Surepass rejected the customer details as invalid. Check the name, mobile number and PAN.',
            self::NoRecordFound => 'Experian has no credit record matching these details.',
            self::ProviderRejected => 'Surepass declined the request. Check the customer details before trying again.',
            self::ProviderUnavailable => 'Surepass is temporarily unavailable. You can retry later.',
            self::ProviderError => 'Surepass returned a server error. It may have processed the request, so this needs reconciliation.',
            self::MalformedResponse => 'Surepass answered with an unreadable response. It may have processed the request, so this needs reconciliation.',
            self::MissingScore => 'Surepass reported success but sent no score. This needs reconciliation before another attempt.',
            self::MissingReportPayload => 'Surepass reported success but sent no report. This needs reconciliation before another attempt.',
            self::MissingPdfLink => 'Surepass reported success but sent no usable PDF link. This needs reconciliation before another attempt.',
            self::PdfUnavailable => 'The PDF Surepass linked to could not be downloaded (the link may have expired). The PDF request itself succeeded; request a new PDF if one is still needed.',
            self::PersistenceFailed => 'The result was received but could not be saved. This needs reconciliation before another attempt.',
            self::QueueStalled => 'The request was never sent to Surepass. You can retry.',
            self::ProcessingStalled => 'The request stopped while waiting for Surepass. It may have been processed, so this needs reconciliation.',
            self::UnexpectedError => 'An unexpected error occurred while contacting Surepass. This needs reconciliation.',
        };
    }
}
