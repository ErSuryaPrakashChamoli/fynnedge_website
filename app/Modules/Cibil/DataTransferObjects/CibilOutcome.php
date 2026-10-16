<?php

namespace App\Modules\Cibil\DataTransferObjects;

use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Models\CibilCreditRequest;

/**
 * How RequestCibilCheck answered: a new paid request was queued, an existing
 * result was reused instead, or the request was blocked because an equivalent
 * one for the same PAN is already in flight. Only Queued costs money.
 */
final readonly class CibilOutcome
{
    public const QUEUED = 'queued';

    public const REUSED = 'reused';

    public const BLOCKED = 'blocked';

    private function __construct(
        public string $kind,
        public CibilCreditRequest $request,
    ) {}

    public static function queued(CibilCreditRequest $request): self
    {
        return new self(self::QUEUED, $request);
    }

    public static function reused(CibilCreditRequest $request): self
    {
        return new self(self::REUSED, $request);
    }

    public static function blocked(CibilCreditRequest $request): self
    {
        return new self(self::BLOCKED, $request);
    }

    public function message(): string
    {
        $noun = $this->request->product->noun();
        $what = $this->request->product === CibilProduct::Score ? 'CIBIL result' : "CIBIL {$noun}";

        return match ($this->kind) {
            self::QUEUED => "CIBIL {$noun} requested. It usually arrives within a minute.",
            self::REUSED => "An {$what} for this PAN from ".$this->request->completed_at?->format('d M Y').' is still within the reuse period, so it is shown instead of paying for a new one.',
            default => 'An equivalent CIBIL request for this PAN is already '.strtolower($this->request->status->getLabel()).'. No second request was sent.',
        };
    }
}
