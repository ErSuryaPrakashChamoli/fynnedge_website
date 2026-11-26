<?php

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\BusinessFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use App\Modules\DebtConsolidation\Services\Finance\FormulaVersion;
use App\Modules\DebtConsolidation\Services\Finance\LiabilityClassifier;
use App\Modules\DebtConsolidation\Services\LiabilityRows;

function routeMatrix(): array
{
    return (require __DIR__.'/../../../config/debt_consolidation.php')['route_liability_types'];
}

function individualCase(array $liabilities, array $scenarios, ?IndividualFinances $finances = null): CaseInput
{
    return new CaseInput(
        applicantType: ApplicantType::Individual,
        liabilities: $liabilities,
        scenarios: $scenarios,
        assumptions: new Assumptions('2026-11-25'),
        scoringConfig: ScoringConfig::defaults(ApplicantType::Individual),
        routeLiabilityTypes: routeMatrix(),
        individualFinances: $finances ?? new IndividualFinances(100000),
    );
}

it('keeps a non-performing loan out of every scenario even when marked for closure', function () {
    $case = individualCase([
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36),
        new LiabilityInput('npa', LiabilityType::PersonalLoan, 'Bank B', 100000, 20, remainingMonths: 12, accountStatus: LiabilityAccountStatus::NonPerforming),
    ], [new ScenarioInput('s', '12 %', 12, 36)]);

    $assessment = (new DebtConsolidationEngine)->assess($case);

    expect($assessment->scenarios[0]['result']->includedKeys)->toBe(['pl'])
        ->and($assessment->liabilities['npa']['classification']->consolidatableNow())->toBeFalse()
        ->and(array_map(fn ($flag) => $flag->flag, $assessment->flags))->toContain(RiskFlag::NotConsolidatable)
        ->and($assessment->portfolio->totalOutstanding)->toBe(600000.0);
});

it('counts kept liabilities in the proposed FOIR', function () {
    $case = individualCase([
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, monthlyPayment: 18076.20, remainingMonths: 36),
        new LiabilityInput('car', LiabilityType::VehicleLoan, 'Bank C', 400000, 9, monthlyPayment: 12000, remainingMonths: 40, mode: ConsolidationMode::Exclude),
    ], [new ScenarioInput('s', '18 % same terms', 18, 36)]);

    $assessment = (new DebtConsolidationEngine)->assess($case);

    // Today 18,076.20 + 12,000; after: same-terms new EMI 18,076.20 + the kept car EMI 12,000.
    expect($assessment->currentAffordability->currentValue)->toBe(30.08)
        ->and($assessment->scenarios[0]['affordability']->proposedValue)->toBe(30.08);
});

it('recommends the best beneficial scenario and none when nothing saves money', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36);

    $assessment = (new DebtConsolidationEngine)->assess(individualCase([$loan], [
        new ScenarioInput('worse', 'Longer', 17, 72),
        new ScenarioInput('better', 'Cheaper', 12, 36),
    ]));
    $nothing = (new DebtConsolidationEngine)->assess(individualCase([$loan], [new ScenarioInput('worse', 'Longer', 17, 72)]));

    expect($assessment->recommendedScenarioKey)->toBe('better')
        ->and($assessment->scenario('worse')['suitability']->band)->toBe(SuitabilityBand::NotBeneficial)
        ->and($nothing->recommendedScenarioKey)->toBeNull();
});

it('assesses businesses on debt-service cover', function () {
    $case = new CaseInput(
        applicantType: ApplicantType::Business,
        liabilities: [new LiabilityInput('bl', LiabilityType::UnsecuredBusinessLoan, 'NBFC', 1000000, 24, remainingMonths: 24)],
        scenarios: [new ScenarioInput('s', 'Term loan', 15, 36, route: FinancingRoute::UnsecuredBusinessLoan)],
        assumptions: new Assumptions('2026-11-25'),
        scoringConfig: ScoringConfig::defaults(ApplicantType::Business),
        routeLiabilityTypes: routeMatrix(),
        businessFinances: new BusinessFinances(800000, 700000),
    );

    $assessment = (new DebtConsolidationEngine)->assess($case);

    expect($assessment->scenarios[0]['affordability']->metric)->toBe('dscr')
        ->and($assessment->scenarios[0]['affordability']->proposedValue)->toBeGreaterThan($assessment->currentAffordability->currentValue);
});

it('reproduces identical results from a stored snapshot of its inputs', function () {
    $case = individualCase([
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36, foreclosureChargePercent: 3),
        new LiabilityInput('cc', LiabilityType::CreditCardOutstanding, 'Card B', 150000, 42, monthlyPayment: 15000, overdueAmount: 2500, daysPastDue: 15),
    ], [new ScenarioInput('s', 'PL', 12, 48, processingFeePercent: 1.5)], new IndividualFinances(90000, 5000, 30000, 1000));

    $stored = json_decode(json_encode($case->toArray()), true);
    $replayed = CaseInput::fromArray($stored);

    expect($replayed->hash())->toBe($case->hash())
        ->and((new DebtConsolidationEngine)->assess($replayed)->toArray())->toBe((new DebtConsolidationEngine)->assess($case)->toArray())
        ->and((new DebtConsolidationEngine)->assess($case)->formulaVersion)->toBe(FormulaVersion::CURRENT);
});

it('separates liabilities no route accepts from those that can be consolidated', function () {
    $classifier = new LiabilityClassifier;
    $assumptions = new Assumptions('2026-11-25');

    $homeLoan = $classifier->classify(new LiabilityInput('h', LiabilityType::HomeLoan, 'Bank', 2000000, 8.5), ApplicantType::Individual, routeMatrix(), $assumptions);
    $nothingAccepts = $classifier->classify(new LiabilityInput('x', LiabilityType::OtherPersonal, 'Friend', 50000, 0), ApplicantType::Individual, [], $assumptions);
    $ninetyDays = $classifier->classify(new LiabilityInput('d', LiabilityType::PersonalLoan, 'Bank', 50000, 16, daysPastDue: 95), ApplicantType::Individual, routeMatrix(), $assumptions);

    expect($homeLoan->eligibleRoutes)->toBe([FinancingRoute::HomeLoanBalanceTransfer])
        ->and($homeLoan->consolidatableNow())->toBeTrue()
        ->and($nothingAccepts->blockers[0]->flag)->toBe(RiskFlag::NoEligibleRoute)
        ->and($ninetyDays->blockers[0]->flag)->toBe(RiskFlag::NotConsolidatable);
});

it('treats a calculator row as secured or unsecured by its current debt type', function () {
    $row = LiabilityRows::blank(ApplicantType::Individual);
    $row['type'] = LiabilityType::HomeLoan->value;
    $row['outstanding'] = '2500000';

    expect(LiabilityRows::toInputs([$row])[0]->isSecured())->toBeTrue();
});
