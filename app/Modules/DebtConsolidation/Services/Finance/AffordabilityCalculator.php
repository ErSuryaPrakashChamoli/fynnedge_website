<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\AffordabilityResult;
use App\Modules\DebtConsolidation\DataTransferObjects\BusinessFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\AffordabilityBand;
use App\Modules\DebtConsolidation\Enums\RiskFlag;

/**
 * Debt service figures passed in already cover EVERY liability of the
 * customer: current = all monthly obligations today; proposed = obligations of
 * liabilities kept + remaining payments after partial closures + the new EMI.
 */
final class AffordabilityCalculator
{
    public function forIndividual(IndividualFinances $finances, float $currentDebtService, float $proposedDebtService, ScoringConfig $config): AffordabilityResult
    {
        $income = $finances->totalIncome();
        $obligations = max(0.0, $finances->otherMonthlyObligations);
        $current = Money::round($currentDebtService + $obligations);
        $proposed = Money::round($proposedDebtService + $obligations);

        if ($income === null) {
            return new AffordabilityResult('foir', null, $current, $proposed, null, null, null, null, AffordabilityBand::Unknown, AffordabilityBand::Unknown, [
                new FlagRaised(RiskFlag::MissingInformation, null, ['field' => 'net_monthly_income']),
            ]);
        }

        $currentFoir = round($current / $income * 100, 2);
        $proposedFoir = round($proposed / $income * 100, 2);
        $living = $finances->monthlyLivingExpenses;
        $proposedBand = $this->foirBand($proposedFoir, $config);
        $flags = in_array($proposedBand, [AffordabilityBand::Stretched, AffordabilityBand::Unaffordable], true)
            ? [new FlagRaised(RiskFlag::HighFoir, null, ['foir' => $proposedFoir])]
            : [];

        return new AffordabilityResult(
            metric: 'foir',
            income: Money::round($income),
            currentDebtService: $current,
            proposedDebtService: $proposed,
            currentValue: $currentFoir,
            proposedValue: $proposedFoir,
            disposableBefore: $living === null ? null : Money::round($income - $living - $current),
            disposableAfter: $living === null ? null : Money::round($income - $living - $proposed),
            currentBand: $this->foirBand($currentFoir, $config),
            proposedBand: $proposedBand,
            flags: $flags,
        );
    }

    public function forBusiness(BusinessFinances $finances, float $currentDebtService, float $proposedDebtService, ScoringConfig $config): AffordabilityResult
    {
        $available = $finances->cashAvailableForDebtService();
        $obligations = max(0.0, $finances->otherMonthlyObligations);
        $current = Money::round($currentDebtService + $obligations);
        $proposed = Money::round($proposedDebtService + $obligations);

        if ($available === null) {
            return new AffordabilityResult('dscr', null, $current, $proposed, null, null, null, null, AffordabilityBand::Unknown, AffordabilityBand::Unknown, [
                new FlagRaised(RiskFlag::MissingInformation, null, ['field' => 'business_cash_flow']),
            ]);
        }

        $currentDscr = $this->dscr($available, $current);
        $proposedDscr = $this->dscr($available, $proposed);
        $proposedBand = $this->dscrBand($proposedDscr, $config);
        $flags = in_array($proposedBand, [AffordabilityBand::Stretched, AffordabilityBand::Unaffordable], true)
            ? [new FlagRaised(RiskFlag::LowDebtServiceCover, null, ['dscr' => $proposedDscr])]
            : [];

        return new AffordabilityResult(
            metric: 'dscr',
            income: Money::round($available),
            currentDebtService: $current,
            proposedDebtService: $proposed,
            currentValue: $currentDscr,
            proposedValue: $proposedDscr,
            disposableBefore: Money::round($available - $current),
            disposableAfter: Money::round($available - $proposed),
            currentBand: $this->dscrBand($currentDscr, $config),
            proposedBand: $proposedBand,
            flags: $flags,
        );
    }

    /**
     * Null when there is no debt to service (coverage is unlimited).
     */
    private function dscr(float $available, float $debtService): ?float
    {
        return $debtService > 0 ? round($available / $debtService, 2) : null;
    }

    private function foirBand(float $foir, ScoringConfig $config): AffordabilityBand
    {
        return match (true) {
            $foir <= $config->threshold('foir_comfortable') => AffordabilityBand::Comfortable,
            $foir <= $config->threshold('foir_manageable') => AffordabilityBand::Manageable,
            $foir <= $config->threshold('foir_stretched') => AffordabilityBand::Stretched,
            default => AffordabilityBand::Unaffordable,
        };
    }

    private function dscrBand(?float $dscr, ScoringConfig $config): AffordabilityBand
    {
        return match (true) {
            $dscr === null, $dscr >= $config->threshold('dscr_comfortable') => AffordabilityBand::Comfortable,
            $dscr >= $config->threshold('dscr_manageable') => AffordabilityBand::Manageable,
            $dscr >= $config->threshold('dscr_stretched') => AffordabilityBand::Stretched,
            default => AffordabilityBand::Unaffordable,
        };
    }
}
