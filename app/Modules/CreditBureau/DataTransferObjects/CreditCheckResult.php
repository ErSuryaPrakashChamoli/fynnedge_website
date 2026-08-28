<?php

namespace App\Modules\CreditBureau\DataTransferObjects;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;

final readonly class CreditCheckResult
{
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public CreditCheckStatus $status,
        public ?int $score = null,
        public ?string $reference = null,
        public ?array $rawResponse = null,
        public ?string $failureReason = null,
    ) {}
}
