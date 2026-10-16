<?php

namespace App\Modules\Experian\DataTransferObjects;

use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Models\ExperianCreditRequest;

/**
 * How RequestExperianCheck answered: a new paid request was queued, an existing
 * result was reused instead, or the request was blocked because an equivalent
 * one for the same PAN is already in flight. Only Queued costs money.
 */
final readonly class ExperianOutcome
{
    public const QUEUED = 'queued';

    public const REUSED = 'reused';

    public const BLOCKED = 'blocked';

    private function __construct(
        public string $kind,
        public ExperianCreditRequest $request,
    ) {}

    public static function queued(ExperianCreditRequest $request): self
    {
        return new self(self::QUEUED, $request);
    }

    public static function reused(ExperianCreditRequest $request): self
    {
        return new self(self::REUSED, $request);
    }

    public static function blocked(ExperianCreditRequest $request): self
    {
        return new self(self::BLOCKED, $request);
    }

    public function message(): string
    {
        $noun = $this->request->product->noun();
        $what = $this->request->product === ExperianProduct::Score ? 'Experian result' : "Experian {$noun}";

        return match ($this->kind) {
            self::QUEUED => "Experian {$noun} requested. It usually arrives within a minute.",
            self::REUSED => "An {$what} for this PAN from ".$this->request->completed_at?->format('d M Y').' is still within the reuse period, so it is shown instead of paying for a new one.',
            default => 'An equivalent Experian request for this PAN is already '.strtolower($this->request->status->getLabel()).'. No second request was sent.',
        };
    }
}
