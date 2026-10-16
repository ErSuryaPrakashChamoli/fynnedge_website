<?php

namespace App\Modules\Cibil\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What was bought from Surepass — each its own paid endpoint:
 *
 *  - Score:  credit-report-cibil/score — the score only.
 *  - Report: credit-report-cibil/fetch-report — the full report JSON (with the score).
 *  - Pdf:    credit-report-cibil/fetch-report-pdf — a temporary link to the
 *            printable report (with the score); its credit_report object may be
 *            empty, so a PDF is never treated as the JSON report.
 *
 * A score result never answers a report or PDF request; a recent report or PDF
 * may answer a score request, since both carry the score.
 */
enum CibilProduct: string implements HasColor, HasLabel
{
    case Score = 'score';
    case Report = 'report';
    case Pdf = 'pdf';

    public function getLabel(): string
    {
        return match ($this) {
            self::Score => 'Score only',
            self::Report => 'Full report',
            self::Pdf => 'PDF report',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Score => 'gray',
            self::Report => 'info',
            self::Pdf => 'primary',
        };
    }

    /** "score", "report" or "PDF" — for messages. */
    public function noun(): string
    {
        return match ($this) {
            self::Score => 'score',
            self::Report => 'report',
            self::Pdf => 'PDF',
        };
    }
}
