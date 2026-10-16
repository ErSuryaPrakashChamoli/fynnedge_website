<?php

namespace App\Modules\Equifax\DataTransferObjects;

use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Models\EquifaxCreditRequest;

/**
 * How RequestEquifaxCheck answered: a new paid request was queued, an existing
 * result was reused instead, or the request was blocked because an equivalent
 * one for the same PAN is already in flight. Only Queued costs money.
 */
final readonly class EquifaxOutcome
{
    public const QUEUED = 'queued';

    public const REUSED = 'reused';

    public const BLOCKED = 'blocked';

    private function __construct(
        public string $kind,
        public EquifaxCreditRequest $request,
    ) {}

    public static function queued(EquifaxCreditRequest $request): self
    {
        return new self(self::QUEUED, $request);
    }

    public static function reused(EquifaxCreditRequest $request): self
    {
        return new self(self::REUSED, $request);
    }

    public static function blocked(EquifaxCreditRequest $request): self
    {
        return new self(self::BLOCKED, $request);
    }

    public function message(): string
    {
        $what = $this->request->product === EquifaxProduct::Report ? 'Equifax report' : 'Equifax result';

        return match ($this->kind) {
            self::QUEUED => 'Equifax '.($this->request->product === EquifaxProduct::Report ? 'report' : 'score').' requested. It usually arrives within a minute.',
            self::REUSED => "An {$what} for this PAN from ".$this->request->completed_at?->format('d M Y').' is still within the reuse period, so it is shown instead of paying for a new one.',
            default => 'An equivalent Equifax request for this PAN is already '.strtolower($this->request->status->getLabel()).'. No second request was sent.',
        };
    }
}
