<?php

namespace App\Modules\FynnOn\DataTransferObjects;

use App\Modules\FynnOn\Enums\FynnOnSyncStatus;

/**
 * The outcome of one POST to FynnOn LMS. `retryable` separates a temporary
 * failure (timeout, 5xx, 429) from one a retry cannot fix (401, 422…).
 * `error` is a short, safe description — never the request body or a token.
 */
final readonly class FynnOnDelivery
{
    public function __construct(
        public FynnOnSyncStatus $status,
        public ?int $httpStatus = null,
        public ?string $lmsReference = null,
        public ?string $error = null,
        public bool $retryable = false,
        public ?int $durationMs = null,
    ) {}

    public function delivered(): bool
    {
        return $this->status->isDelivered();
    }

    /**
     * @return array<string, mixed>
     */
    public function toLog(int $attempt, string $event, string $reference): array
    {
        return array_filter([
            'event' => $event,
            'reference' => $reference,
            'attempt' => $attempt,
            'http_status' => $this->httpStatus,
            'lms_reference' => $this->lmsReference,
            'error' => $this->error,
            'duration_ms' => $this->durationMs,
        ], fn (mixed $value): bool => $value !== null);
    }
}
