<?php

namespace App\Modules\MobileOtp\DataTransferObjects;

use App\Modules\MobileOtp\Enums\SmsFailureReason;
use App\Modules\MobileOtp\Enums\SmsSendStatus;

/**
 * A provider's answer, normalised so nothing above the provider ever handles a
 * raw response body. Every field here is safe to store and show to an admin:
 * no credentials, no message text, no code.
 */
final readonly class SmsSendResult
{
    public function __construct(
        public SmsSendStatus $status,
        public ?string $providerReference = null,
        public ?SmsFailureReason $failureReason = null,
        public ?int $httpStatus = null,
    ) {}

    public static function accepted(?string $providerReference = null, ?int $httpStatus = null): self
    {
        return new self(SmsSendStatus::Accepted, $providerReference, null, $httpStatus);
    }

    public static function unconfirmed(SmsFailureReason $reason, ?string $providerReference = null, ?int $httpStatus = null): self
    {
        return new self(SmsSendStatus::Unconfirmed, $providerReference, $reason, $httpStatus);
    }

    public static function failed(SmsFailureReason $reason, ?int $httpStatus = null): self
    {
        return new self(SmsSendStatus::Failed, null, $reason, $httpStatus);
    }

    public function isFailure(): bool
    {
        return $this->status === SmsSendStatus::Failed;
    }
}
