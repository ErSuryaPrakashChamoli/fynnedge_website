<?php

namespace App\Modules\Cibil\DataTransferObjects;

use App\Modules\Cibil\Enums\CibilErrorCode;

/**
 * What one Surepass CIBIL call came back with. A failure carries only a safe
 * error code, the HTTP status and Surepass's message_code — never the body,
 * which may echo the customer's details back.
 */
final readonly class SurepassCibilResult
{
    /**
     * @param  array<string, mixed>|null  $body  The complete decoded response, on success only.
     */
    private function __construct(
        public ?CibilErrorCode $errorCode,
        public ?int $httpStatus,
        public ?array $body,
        public ?string $messageCode,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function success(array $body, int $httpStatus, ?string $messageCode): self
    {
        return new self(null, $httpStatus, $body, $messageCode);
    }

    public static function failed(CibilErrorCode $errorCode, ?int $httpStatus = null, ?string $messageCode = null): self
    {
        return new self($errorCode, $httpStatus, null, $messageCode);
    }

    public function isSuccessful(): bool
    {
        return $this->errorCode === null;
    }

    /**
     * Surepass may have processed (and billed) the call even though we have no result.
     */
    public function isUncertain(): bool
    {
        return $this->errorCode?->isUncertain() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $data = $this->body['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
