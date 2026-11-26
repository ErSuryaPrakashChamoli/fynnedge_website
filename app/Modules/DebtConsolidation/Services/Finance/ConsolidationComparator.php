<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\CashFlowSchedule;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioResult;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use InvalidArgumentException;

/**
 * Compares keeping the liabilities in scope with closing them through one
 * proposed loan (see ScenarioResult for the accounting identity).
 *
 * Loan amount: what must be paid to the existing lenders (closed principal +
 * dues, + closure charges when financed). When the lender deducts the
 * processing fee and its GST from the payout, the loan is grossed up so the
 * payout still covers the closures: L is the smallest whole-rupee amount
 * whose payout, L − fee − GST on fee (each rounded to the paisa as the lender
 * charges them), covers R. It sits at or next to
 *   (R + F·(1+t)) / (1 − p·(1+t)), rounded up to the next rupee
 * where R is the amount to finance, p the fee rate, F the fixed fee and t the
 * GST rate. A sanctioned amount (loanAmountOverride) replaces L; any gap is a
 * shortfall the customer pays upfront.
 */
final class ConsolidationComparator
{
    public function __construct(
        private readonly LiabilityProjector $projector = new LiabilityProjector,
        private readonly ForeclosureEstimator $foreclosure = new ForeclosureEstimator,
    ) {}

    /**
     * @param  list<LiabilityInput>  $liabilities
     */
    public function compare(array $liabilities, ScenarioInput $scenario, Assumptions $assumptions): ScenarioResult
    {
        if ($scenario->tenureMonths < 1 || $scenario->tenureMonths > $assumptions->maxProjectionMonths) {
            throw new InvalidArgumentException('The proposed tenure must be between 1 and '.$assumptions->maxProjectionMonths.' months.');
        }

        if ($scenario->annualInterestRate < 0) {
            throw new InvalidArgumentException('The proposed interest rate cannot be negative.');
        }

        $flags = [];
        $existing = new CashFlowSchedule;
        $residual = new CashFlowSchedule;
        $includedKeys = [];
        $lines = [];
        $principalClosed = 0.0;
        $dues = 0.0;
        $immediateDues = 0.0;
        $closureCosts = 0.0;
        $existingMonthly = 0.0;
        $residualMonthly = 0.0;
        $rateWeight = 0.0;
        $securesUnsecuredDebt = false;
        $losesTaxBenefit = false;

        foreach ($liabilities as $original) {
            $liability = $scenario->applyTo($original);

            if (! $liability->mode->isIncluded()) {
                continue;
            }

            $estimate = $this->foreclosure->estimate($liability, $assumptions);

            if ($estimate === null) {
                $flags[] = new FlagRaised(RiskFlag::MissingInformation, $liability->key, ['field' => 'partial_amount']);

                continue;
            }

            $projection = $this->projector->project($liability, $scenario->tenureMonths, $assumptions);
            $flags = [...$flags, ...$estimate->flags, ...$projection->flags];
            $existing = $existing->plus($projection->schedule);
            $existingMonthly += $projection->schedule->firstPayment();

            $residualRepayment = 0.0;

            if (! $estimate->isFullClosure) {
                $left = $this->projector->project($liability, $scenario->tenureMonths, $assumptions, $liability->outstandingPrincipal - $estimate->principal);
                $residual = $residual->plus($left->schedule);
                $residualMonthly += $left->schedule->firstPayment();
                $residualRepayment = $left->schedule->total();
            }

            $includedKeys[] = $liability->key;
            $principalClosed += $estimate->principal;
            $dues += $estimate->dues();
            $immediateDues += $estimate->immediateDues();
            $closureCosts += $estimate->closureCosts();
            $rateWeight += $estimate->principal * $projection->effectiveAnnualRate;
            $securesUnsecuredDebt = $securesUnsecuredDebt || (($scenario->route?->isSecured() ?? false) && ! $liability->isSecured());
            $losesTaxBenefit = $losesTaxBenefit || in_array($liability->type, [LiabilityType::EducationLoan, LiabilityType::HomeLoan], true);

            $lines[] = [
                'key' => $liability->key,
                'lender_name' => $liability->lenderName,
                'type' => $liability->type->value,
                'mode' => $liability->mode->value,
                'outstanding_principal' => Money::round($liability->outstandingPrincipal),
                'principal_closed' => $estimate->principal,
                'closure_total' => $estimate->total,
                'closure_costs' => $estimate->closureCosts(),
                'closure_source' => $estimate->source,
                'closure_verified' => $estimate->verified,
                'current_monthly_payment' => $projection->schedule->firstPayment(),
                'effective_annual_rate' => round($projection->effectiveAnnualRate, 2),
                'existing_remaining_repayment' => $projection->schedule->total(),
                'existing_remaining_interest' => $projection->schedule->totalInterest(),
                'existing_remaining_months' => $projection->schedule->months(),
                'residual_repayment' => $residualRepayment,
            ];
        }

        $principalClosed = Money::round($principalClosed);
        $dues = Money::round($dues);
        $immediateDues = Money::round($immediateDues);
        $closureCosts = Money::round($closureCosts);
        $toFinance = Money::round($principalClosed + $dues + ($scenario->closureCostsFinanced ? $closureCosts : 0.0));

        $feeRate = max(0.0, $scenario->processingFeePercent) / 100;
        $fixedFee = max(0.0, $scenario->processingFeeFixed);
        $taxRate = $assumptions->gstRatePercent / 100;

        if ($includedKeys === []) {
            $loanAmount = 0.0;
        } elseif ($scenario->loanAmountOverride !== null) {
            $loanAmount = Money::round(max(0.0, $scenario->loanAmountOverride));
        } elseif ($scenario->feesDeductedFromDisbursal) {
            $denominator = 1 - $feeRate * (1 + $taxRate);

            if ($denominator <= 0) {
                throw new InvalidArgumentException('The processing fee cannot consume the whole loan.');
            }

            $loanAmount = $this->smallestCoveringLoan($toFinance, $scenario->processingFeePercent, $fixedFee, $assumptions->gstRatePercent, $denominator);
        } else {
            $loanAmount = $this->wholeRupeesUp($toFinance);
        }

        $processingFee = $loanAmount > 0 ? Money::round(Money::percentage($loanAmount, max(0.0, $scenario->processingFeePercent)) + $fixedFee) : 0.0;
        $processingFeeTax = Money::percentage($processingFee, $assumptions->gstRatePercent);
        $netDisbursal = Money::round($scenario->feesDeductedFromDisbursal ? $loanAmount - $processingFee - $processingFeeTax : $loanAmount);
        $shortfall = Money::round(max(0.0, $toFinance - $netDisbursal));
        $surplus = Money::round(max(0.0, $netDisbursal - $toFinance));
        $otherUpfront = $loanAmount > 0 ? Money::round(max(0.0, $scenario->otherUpfrontCosts)) : 0.0;

        $upfrontCash = Money::round(
            ($scenario->feesDeductedFromDisbursal ? 0.0 : $processingFee + $processingFeeTax)
            + ($scenario->closureCostsFinanced ? 0.0 : $closureCosts)
            + $otherUpfront + $shortfall - $surplus
        );

        $newEmi = Amortisation::emi($loanAmount, $scenario->annualInterestRate, $scenario->tenureMonths);
        $newLoan = $loanAmount > 0
            ? Amortisation::termSchedule($loanAmount, $scenario->annualInterestRate, $newEmi, $scenario->tenureMonths)
            : new CashFlowSchedule;
        $proposed = $newLoan->plus($residual);

        $existingTotal = Money::round($immediateDues + $existing->total());
        $proposedTotal = Money::round($upfrontCash + $proposed->total());
        $net = Money::round($existingTotal - $proposedTotal);
        $existingInterest = $existing->totalInterest();
        $proposedInterest = $proposed->totalInterest();
        $totalCosts = Money::round($closureCosts + $processingFee + $processingFeeTax + $otherUpfront);
        $proposedMonthly = Money::round($newLoan->firstPayment() + $residualMonthly);
        $monthlyDifference = Money::round($existingMonthly - $proposedMonthly);
        $tenureChange = $proposed->months() - $existing->months();
        $weightedRate = $principalClosed > 0 ? $rateWeight / $principalClosed : 0.0;
        $rateImprovement = round($weightedRate - $scenario->annualInterestRate, 2);

        [$costBreakEven, $costHolds] = $this->costBreakEven($existing, $proposed, $totalCosts);
        $cashBreakEven = $this->cashBreakEven($existing, $proposed, $immediateDues - $upfrontCash);

        if ($includedKeys !== []) {
            $flags = [...$flags, ...$this->scenarioFlags($net, $monthlyDifference, $tenureChange, $shortfall, $surplus, $rateImprovement, $securesUnsecuredDebt, $losesTaxBenefit)];
        } else {
            $flags[] = new FlagRaised(RiskFlag::MissingInformation, null, ['field' => 'liabilities']);
        }

        return new ScenarioResult(
            scenarioKey: $scenario->key,
            label: $scenario->label,
            annualInterestRate: $scenario->annualInterestRate,
            tenureMonths: $scenario->tenureMonths,
            includedKeys: $includedKeys,
            principalClosed: $principalClosed,
            duesCleared: $dues,
            closureCosts: $closureCosts,
            closureRequirement: Money::round($principalClosed + $dues + $closureCosts),
            closureCostsFinanced: $scenario->closureCostsFinanced,
            loanAmount: $loanAmount,
            processingFee: $processingFee,
            processingFeeTax: $processingFeeTax,
            otherUpfrontCosts: $otherUpfront,
            netDisbursal: $netDisbursal,
            shortfall: $shortfall,
            surplus: $surplus,
            newEmi: $newEmi,
            newLoanTotalRepayment: $newLoan->total(),
            newLoanInterest: $newLoan->totalInterest(),
            existingMonthlyOutflow: Money::round($existingMonthly),
            existingTotalOutflow: $existingTotal,
            existingInterest: $existingInterest,
            existingMonths: $existing->months(),
            proposedMonthlyOutflow: $proposedMonthly,
            proposedTotalOutflow: $proposedTotal,
            proposedInterest: $proposedInterest,
            proposedMonths: $proposed->months(),
            upfrontCash: $upfrontCash,
            residualRepayment: $residual->total(),
            monthlyCashFlowDifference: $monthlyDifference,
            netFinancialBenefit: $net,
            benefitPercent: Money::percentOf($net, $existingTotal),
            interestSaving: Money::round($existingInterest - $proposedInterest),
            totalConsolidationCosts: $totalCosts,
            tenureChangeMonths: $tenureChange,
            costBreakEvenMonth: $costBreakEven,
            costRecoveryHolds: $costHolds,
            cashBreakEvenMonth: $cashBreakEven,
            weightedExistingRate: round($weightedRate, 2),
            rateImprovementPoints: $rateImprovement,
            lines: $lines,
            yearly: $this->yearly($existing, $proposed, $immediateDues, $upfrontCash),
            flags: $flags,
        );
    }

    /**
     * First month in which the interest saved so far covers every cost of
     * consolidating (charges, taxes, fees — financed or not), and whether the
     * saving still covers them once both repayments have ended.
     *
     * @return array{0: ?int, 1: bool}
     */
    private function costBreakEven(CashFlowSchedule $existing, CashFlowSchedule $proposed, float $totalCosts): array
    {
        $months = max($existing->months(), $proposed->months());
        $saved = 0.0;
        $first = $totalCosts <= 0 ? 0 : null;

        for ($month = 1; $month <= $months; $month++) {
            $saved += $existing->interestAt($month) - $proposed->interestAt($month);

            if ($first === null && Money::round($saved) >= $totalCosts) {
                $first = $month;
            }
        }

        return [$first, Money::round($saved) >= $totalCosts];
    }

    /**
     * First month in which the cash paid under the proposal (including any
     * upfront cash) is no more than the cash that would have been paid anyway.
     */
    private function cashBreakEven(CashFlowSchedule $existing, CashFlowSchedule $proposed, float $openingDifference): ?int
    {
        $cumulative = Money::round($openingDifference);

        if ($cumulative >= 0) {
            return 0;
        }

        $months = max($existing->months(), $proposed->months());

        for ($month = 1; $month <= $months; $month++) {
            $cumulative = Money::round($cumulative + $existing->paymentAt($month) - $proposed->paymentAt($month));

            if ($cumulative >= 0) {
                return $month;
            }
        }

        return null;
    }

    /**
     * @return list<array{year: int, existing: float, proposed: float, cumulative_existing: float, cumulative_proposed: float}>
     */
    private function yearly(CashFlowSchedule $existing, CashFlowSchedule $proposed, float $immediateDues, float $upfrontCash): array
    {
        $existingYears = $existing->yearlyPayments();
        $proposedYears = $proposed->yearlyPayments();
        $years = max(count($existingYears), count($proposedYears), 1);
        $cumulativeExisting = 0.0;
        $cumulativeProposed = 0.0;
        $rows = [];

        for ($year = 1; $year <= $years; $year++) {
            $existingPaid = Money::round(($existingYears[$year - 1] ?? 0.0) + ($year === 1 ? $immediateDues : 0.0));
            $proposedPaid = Money::round(($proposedYears[$year - 1] ?? 0.0) + ($year === 1 ? $upfrontCash : 0.0));
            $cumulativeExisting = Money::round($cumulativeExisting + $existingPaid);
            $cumulativeProposed = Money::round($cumulativeProposed + $proposedPaid);

            $rows[] = [
                'year' => $year,
                'existing' => $existingPaid,
                'proposed' => $proposedPaid,
                'cumulative_existing' => $cumulativeExisting,
                'cumulative_proposed' => $cumulativeProposed,
            ];
        }

        return $rows;
    }

    /**
     * @return list<FlagRaised>
     */
    private function scenarioFlags(float $net, float $monthlyDifference, int $tenureChange, float $shortfall, float $surplus, float $rateImprovement, bool $securesUnsecuredDebt, bool $losesTaxBenefit): array
    {
        $flags = [];

        if ($net < 0) {
            $flags[] = new FlagRaised(RiskFlag::NegativeSavings, null, ['net_benefit' => $net]);

            if ($monthlyDifference > 0) {
                $flags[] = new FlagRaised(RiskFlag::LowerEmiHigherCost, null, ['monthly_difference' => $monthlyDifference, 'net_benefit' => $net]);
            }
        }

        if ($tenureChange > 0) {
            $flags[] = new FlagRaised(RiskFlag::TenureExtended, null, ['months' => $tenureChange]);
        }

        if ($shortfall > 0) {
            $flags[] = new FlagRaised(RiskFlag::LoanShortfall, null, ['amount' => $shortfall]);
        }

        if ($surplus > 1.0) {
            $flags[] = new FlagRaised(RiskFlag::ExtraBorrowing, null, ['amount' => $surplus]);
        }

        if ($rateImprovement <= 0) {
            $flags[] = new FlagRaised(RiskFlag::ProposedRateNotLower, null, ['points' => $rateImprovement]);
        }

        if ($securesUnsecuredDebt) {
            $flags[] = new FlagRaised(RiskFlag::SecuringUnsecuredDebt);
        }

        if ($losesTaxBenefit) {
            $flags[] = new FlagRaised(RiskFlag::TaxBenefitLoss);
        }

        return $flags;
    }

    private function wholeRupeesUp(float $amount): float
    {
        return (float) ceil(Money::round($amount) - 0.000001);
    }

    /**
     * Starts from the formula, rounded up, and steps a rupee either way so the
     * paisa rounding of the fee and its GST never leaves the payout a paisa
     * short (a false "shortfall") nor borrows a rupee more than needed.
     */
    private function smallestCoveringLoan(float $toFinance, float $feePercent, float $fixedFee, float $gstPercent, float $denominator): float
    {
        $needed = (int) round($toFinance * 100);
        $covers = function (float $loan) use ($feePercent, $fixedFee, $gstPercent, $needed): bool {
            $fee = Money::round(Money::percentage($loan, max(0.0, $feePercent)) + $fixedFee);

            return (int) round(($loan - $fee - Money::percentage($fee, $gstPercent)) * 100) >= $needed;
        };

        $loan = (float) ceil(($toFinance + $fixedFee * (1 + $gstPercent / 100)) / $denominator - 0.000001);

        for ($step = 0; $step < 5 && $loan > 1 && $covers($loan - 1); $step++) {
            $loan--;
        }

        for ($step = 0; $step < 5 && ! $covers($loan); $step++) {
            $loan++;
        }

        return $loan;
    }
}
