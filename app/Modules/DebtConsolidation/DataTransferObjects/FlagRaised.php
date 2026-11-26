<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\RiskFlag;

/**
 * One risk flag raised by the engine, optionally about one liability, with
 * the figures that explain it.
 */
final readonly class FlagRaised
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public RiskFlag $flag,
        public ?string $liabilityKey = null,
        public array $context = [],
    ) {}

    /**
     * @return array{flag: string, severity: string, liability_key: ?string, context: array<string, scalar|null>}
     */
    public function toArray(): array
    {
        return [
            'flag' => $this->flag->value,
            'severity' => $this->flag->severity(),
            'liability_key' => $this->liabilityKey,
            'context' => $this->context,
        ];
    }
}
