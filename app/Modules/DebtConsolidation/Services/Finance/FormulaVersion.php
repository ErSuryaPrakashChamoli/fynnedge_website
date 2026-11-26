<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

/**
 * The version stamped on every calculation snapshot and report. Bump it on
 * ANY change that can alter a figure for the same inputs, and record the
 * change in docs/debt-consolidation/06-financial-engine.md. Old snapshots
 * keep their stored results; they are never recalculated silently.
 *
 * 1.0.0 (2026-11-25) — first release: paisa-rounded amortisation, implied
 * rates from EMI × remaining months, closure gross-up for deducted fees,
 * nominal total-outflow comparison, interest-based cost break-even.
 */
final class FormulaVersion
{
    public const CURRENT = '1.0.0';
}
