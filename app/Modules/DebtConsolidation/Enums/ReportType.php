<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportType: string implements HasLabel
{
    case PreliminaryAssessment = 'preliminary_assessment';
    case PortfolioSummary = 'portfolio_summary';
    case Suitability = 'suitability';
    case SavingsComparison = 'savings_comparison';
    case ForeclosureAnalysis = 'foreclosure_analysis';
    case AdvisorAssessment = 'advisor_assessment';
    case LenderApplicationSummary = 'lender_application_summary';
    case ClosureReport = 'closure_report';

    public function getLabel(): string
    {
        return match ($this) {
            self::PreliminaryAssessment => __('Preliminary Debt Assessment'),
            self::PortfolioSummary => __('Debt Portfolio Summary'),
            self::Suitability => __('Consolidation Suitability'),
            self::SavingsComparison => __('EMI and Savings Comparison'),
            self::ForeclosureAnalysis => __('Foreclosure Cost Analysis'),
            self::AdvisorAssessment => __('Internal Advisor Assessment'),
            self::LenderApplicationSummary => __('Lender Application Summary'),
            self::ClosureReport => __('Final Debt Closure Report'),
        };
    }

    /**
     * The internal advisor report is for staff only; every other report may be
     * released to the customer by staff.
     */
    public function isInternal(): bool
    {
        return $this === self::AdvisorAssessment;
    }

    /**
     * Whether the report is built from a calculation snapshot (and so needs one).
     */
    public function needsSnapshot(): bool
    {
        return ! in_array($this, [self::LenderApplicationSummary, self::ClosureReport], true);
    }
}
