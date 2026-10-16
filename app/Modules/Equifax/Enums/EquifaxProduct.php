<?php

namespace App\Modules\Equifax\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What was bought from Surepass: a score only (credit-report-v2/score) or the
 * full credit report (credit-report-v2/fetch-report). A score result is never
 * treated as a full report; a recent full report may answer a score request,
 * since it carries the score.
 */
enum EquifaxProduct: string implements HasColor, HasLabel
{
    case Score = 'score';
    case Report = 'report';

    public function getLabel(): string
    {
        return match ($this) {
            self::Score => 'Score only',
            self::Report => 'Full report',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Score => 'gray',
            self::Report => 'info',
        };
    }
}
