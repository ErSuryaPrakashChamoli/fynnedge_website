<?php

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioResult;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Services\Finance\ConsolidationComparator;

function compareScenario(array $liabilities, ScenarioInput $scenario): ScenarioResult
{
    return (new ConsolidationComparator)->compare($liabilities, $scenario, new Assumptions('2026-11-25'));
}

function raisedFlags(ScenarioResult $result): array
{
    return array_map(fn ($flag) => $flag->flag, $result->flags);
}

it('reports no saving when the new loan repeats the old loan exactly, counting principal once', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36);

    $result = compareScenario([$loan], new ScenarioInput('same', 'Same terms', 18, 36));

    expect($result->loanAmount)->toBe(500000.0)
        ->and($result->existingTotalOutflow)->toBe(650743.11)
        ->and($result->proposedTotalOutflow)->toBe(650743.11)
        ->and($result->netFinancialBenefit)->toBe(0.0)
        ->and($result->monthlyCashFlowDifference)->toBe(0.0)
        ->and($result->costBreakEvenMonth)->toBe(0);
});

it('grosses up the loan for a deducted fee and compares total outflows', function () {
    $liabilities = [
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36),
        new LiabilityInput('cc', LiabilityType::CreditCardOutstanding, 'Card B', 150000, 42, monthlyPayment: 15000),
    ];

    $result = compareScenario($liabilities, new ScenarioInput('s1', 'PL 12 %', 12, 48, processingFeePercent: 1.5));

    // Reference values from an independent Decimal implementation (half-up per month).
    expect($result->loanAmount)->toBe(661713.0)
        ->and($result->processingFee)->toBe(9925.7)
        ->and($result->processingFeeTax)->toBe(1786.63)
        ->and($result->netDisbursal)->toBe(650000.67)
        ->and($result->newEmi)->toBe(17425.44)
        ->and($result->existingTotalOutflow)->toBe(838641.07)
        ->and($result->proposedTotalOutflow)->toBe(836420.58)
        ->and($result->netFinancialBenefit)->toBe(2220.49)
        ->and($result->monthlyCashFlowDifference)->toBe(15650.76)
        ->and($result->tenureChangeMonths)->toBe(12)
        ->and(raisedFlags($result))->toContain(RiskFlag::TenureExtended);
});

it('never calls a lower EMI a saving when the total cost goes up', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 14, remainingMonths: 24);

    $result = compareScenario([$loan], new ScenarioInput('long', '13 % over 5 years', 13, 60));

    // EMI falls from ₹24,006.44 to ₹11,376.54 but ₹1,06,437.47 more is repaid in total.
    expect($result->monthlyCashFlowDifference)->toBe(12629.9)
        ->and($result->netFinancialBenefit)->toBe(-106437.47)
        ->and($result->isBeneficial())->toBeFalse()
        ->and(raisedFlags($result))->toContain(RiskFlag::NegativeSavings, RiskFlag::LowerEmiHigherCost, RiskFlag::TenureExtended);
});

it('takes a processing fee paid upfront straight off the benefit', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36);

    $result = compareScenario([$loan], new ScenarioInput('fee', 'Same terms, 2 % fee', 18, 36, processingFeePercent: 2, feesDeductedFromDisbursal: false));

    expect($result->loanAmount)->toBe(500000.0)
        ->and($result->upfrontCash)->toBe(11800.0)
        ->and($result->netFinancialBenefit)->toBe(-11800.0)
        ->and($result->costBreakEvenMonth)->toBeNull()
        ->and($result->costRecoveryHolds)->toBeFalse();
});

it('makes the customer pay closure charges upfront when they are not financed', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 200000, 16, remainingMonths: 24, foreclosureChargePercent: 3);

    $result = compareScenario([$loan], new ScenarioInput('cash', '14 %', 14, 24, closureCostsFinanced: false));

    // 3 % of 2,00,000 = 6,000 + 18 % GST = 7,080, more than two years at 2 points lower saves.
    expect($result->loanAmount)->toBe(200000.0)
        ->and($result->upfrontCash)->toBe(7080.0)
        ->and($result->netFinancialBenefit)->toBe(-2518.91);
});

it('records a shortfall when the sanctioned amount does not cover the closures', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36);

    $result = compareScenario([$loan], new ScenarioInput('sanction', 'Sanctioned', 12, 36, loanAmountOverride: 450000));

    expect($result->shortfall)->toBe(50000.0)
        ->and($result->upfrontCash)->toBe(50000.0)
        ->and(raisedFlags($result))->toContain(RiskFlag::LoanShortfall);
});

it('keeps paying what is left of a partly consolidated loan', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 400000, 15, remainingMonths: 48, mode: ConsolidationMode::Partial, partialAmount: 100000);

    $result = compareScenario([$loan], new ScenarioInput('part', '12 %', 12, 48));

    // Existing 5,34,350.37; proposed 1,26,402.50 (new loan) + 3,68,107.14 (rest of the old loan).
    expect($result->loanAmount)->toBe(100000.0)
        ->and($result->residualRepayment)->toBe(368107.14)
        ->and($result->netFinancialBenefit)->toBe(39840.73)
        ->and($result->monthlyCashFlowDifference)->toBe(-2633.38);
});

it('leaves kept liabilities out of the comparison', function () {
    $liabilities = [
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36),
        new LiabilityInput('home', LiabilityType::HomeLoan, 'Bank H', 2500000, 8.5, remainingMonths: 200, mode: ConsolidationMode::Exclude),
    ];

    $result = compareScenario($liabilities, new ScenarioInput('same', 'Same terms', 18, 36));

    expect($result->includedKeys)->toBe(['pl'])
        ->and($result->existingTotalOutflow)->toBe(650743.11);
});

it('lets a scenario close a different set of liabilities', function () {
    $liabilities = [
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36),
        new LiabilityInput('pl2', LiabilityType::PersonalLoan, 'Bank B', 100000, 20, remainingMonths: 12),
    ];

    $result = compareScenario($liabilities, new ScenarioInput('one', 'Only Bank A', 18, 36, liabilityOverrides: ['pl2' => ['mode' => 'exclude']]));

    expect($result->includedKeys)->toBe(['pl'])
        ->and($result->loanAmount)->toBe(500000.0);
});

it('warns when unsecured debt would move onto a secured loan', function () {
    $loan = new LiabilityInput('cc', LiabilityType::CreditCardOutstanding, 'Card', 300000, 42, monthlyPayment: 20000);

    $result = compareScenario([$loan], new ScenarioInput('lap', 'LAP', 10, 60, route: FinancingRoute::LoanAgainstProperty));

    expect(raisedFlags($result))->toContain(RiskFlag::SecuringUnsecuredDebt);
});

it('finds the month in which interest saved covers the costs', function () {
    // 5,00,000 at 18 % → 12 %, same 36 months, 1 % fee (+GST) paid upfront: ₹5,900 of costs.
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36);

    $result = compareScenario([$loan], new ScenarioInput('cut', '12 %', 12, 36, processingFeePercent: 1, feesDeductedFromDisbursal: false));

    // Months 1–2 save about ₹4,957 of interest (7,500 − 5,000, then 7,341 − 4,884); month 3 passes ₹5,900.
    expect($result->totalConsolidationCosts)->toBe(5900.0)
        ->and($result->costBreakEvenMonth)->toBe(3)
        ->and($result->costRecoveryHolds)->toBeTrue()
        ->and($result->netFinancialBenefit)->toBeGreaterThan(0.0);
});

it('refuses a tenure of zero months', function () {
    compareScenario([], new ScenarioInput('bad', 'Bad', 12, 0));
})->throws(InvalidArgumentException::class);

it('borrows the smallest whole rupee whose payout after the fee and its GST covers the closures', function (float $outstanding, float $feePercent, float $loanAmount, float $netDisbursal) {
    // Expected values from the independent Decimal reference (docs/debt-consolidation/validation/reference.py).
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', $outstanding, 18, remainingMonths: 24);

    $result = compareScenario([$loan], new ScenarioInput('s1', 'PL', 14, 48, processingFeePercent: $feePercent));

    expect($result->loanAmount)->toBe($loanAmount)
        ->and($result->netDisbursal)->toBe($netDisbursal)
        ->and($result->shortfall)->toBe(0.0)
        ->and(raisedFlags($result))->not->toContain(RiskFlag::LoanShortfall);
})->with([
    // The formula gives 4,64,195.002, yet 4,64,195 already pays out exactly 4,53,240.
    'rounding lets one rupee less cover' => [453240.0, 2.0, 464195.0, 453240.0],
    // Rounding the formula to the paisa first used to stop at 1,01,017, a paisa short.
    'never a paisa short' => [100421.0, 0.5, 101018.0, 100421.99],
]);
