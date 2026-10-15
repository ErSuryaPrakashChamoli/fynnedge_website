<?php

namespace App\Modules\CreditBureau\DataTransferObjects;

use App\Modules\CreditBureau\Models\CrifReport;

/**
 * How RequestCrifReport answered: a new paid request was queued, an existing
 * report was reused instead, or the request was blocked because one for the
 * same PAN is already in flight. Only Queued costs money.
 */
final readonly class CrifRequestOutcome
{
    public const QUEUED = 'queued';

    public const REUSED = 'reused';

    public const BLOCKED = 'blocked';

    private function __construct(
        public string $kind,
        public CrifReport $report,
    ) {}

    public static function queued(CrifReport $report): self
    {
        return new self(self::QUEUED, $report);
    }

    public static function reused(CrifReport $report): self
    {
        return new self(self::REUSED, $report);
    }

    public static function blocked(CrifReport $report): self
    {
        return new self(self::BLOCKED, $report);
    }

    public function message(): string
    {
        return match ($this->kind) {
            self::QUEUED => 'CRIF report requested. It usually arrives within a minute.',
            self::REUSED => 'A CRIF report for this PAN from '.$this->report->completed_at?->format('d M Y').' is still within the reuse period, so it is shown instead of paying for a new one.',
            default => 'A CRIF request for this PAN is already '.strtolower($this->report->status->getLabel()).'. No second request was sent.',
        };
    }
}
