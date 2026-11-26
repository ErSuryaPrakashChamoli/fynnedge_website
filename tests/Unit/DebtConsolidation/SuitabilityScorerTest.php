<?php

use App\Modules\DebtConsolidation\DataTransferObjects\AffordabilityResult;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioResult;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\AffordabilityBand;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;
use App\Modules\DebtConsolidation\Services\Finance\SuitabilityScorer;

function scoredScenario(float $net, float $existingTotal, float $rateImprovement, float $costs, float $loan, int $tenureChange): ScenarioResult
{
    return new ScenarioResult(
        scenarioKey: 's', label: 's', annualInterestRate: 12, tenureMonths: 36, includedKeys: ['a'],
        principalClosed: $loan, duesCleared: 0, closureCosts: 0, closureRequirement: $loan, closureCostsFinanced: true,
        loanAmount: $loan, processingFee: $costs, processingFeeTax: 0, otherUpfrontCosts: 0, netDisbursal: $loan,
        shortfall: 0, surplus: 0, newEmi: 0, newLoanTotalRepayment: 0, newLoanInterest: 0,
        existingMonthlyOutflow: 0, existingTotalOutflow: $existingTotal, existingInterest: 0, existingMonths: 36,
        proposedMonthlyOutflow: 0, proposedTotalOutflow: $existingTotal - $net, proposedInterest: 0, proposedMonths: 36 + $tenureChange,
        upfrontCash: 0, residualRepayment: 0, monthlyCashFlowDifference: 0, netFinancialBenefit: $net,
        benefitPercent: round($net / $existingTotal * 100, 2), interestSaving: 0, totalConsolidationCosts: $costs,
        tenureChangeMonths: $tenureChange, costBreakEvenMonth: null, costRecoveryHolds: true, cashBreakEvenMonth: null,
        weightedExistingRate: 12 + $rateImprovement, rateImprovementPoints: $rateImprovement, lines: [], yearly: [], flags: [],
    );
}

function individualAffordability(?float $proposedFoir): AffordabilityResult
{
    return new AffordabilityResult('foir', $proposedFoir === null ? null : 100000, 0, 0, null, $proposedFoir, null, null, AffordabilityBand::Unknown, AffordabilityBand::Unknown);
}

it('scores every dimension at full marks for an excellent consolidation', function () {
    // 12 % benefit, 8 points cheaper, 1 % costs, no extra months, FOIR 35 %.
    $result = (new SuitabilityScorer)->score(scoredScenario(120000, 1000000, 8, 5000, 500000, 0), individualAffordability(35), ScoringConfig::defaults(ApplicantType::Individual), []);

    expect($result->score)->toBe(100.0)
        ->and($result->band)->toBe(SuitabilityBand::PotentiallySuitable)
        ->and($result->requiresAdvisorReview)->toBeFalse();
});

it('interpolates each dimension between its thresholds and applies the mandate weights', function () {
    // Benefit 5 % → 50; FOIR 55 % → 50; 4 points → 50; costs 3.5 % → 50; 18 extra months → 50.
    $result = (new SuitabilityScorer)->score(scoredScenario(50000, 1000000, 4, 17500, 500000, 18), individualAffordability(55), ScoringConfig::defaults(ApplicantType::Individual), []);

    expect($result->dimensions['financial_benefit'])->toMatchArray(['score' => 50.0, 'weight' => 30.0, 'weighted' => 15.0])
        ->and($result->dimensions['affordability']['weighted'])->toBe(12.5)
        ->and($result->dimensions['interest_improvement']['weighted'])->toBe(10.0)
        ->and($result->dimensions['cost_efficiency']['weighted'])->toBe(7.5)
        ->and($result->dimensions['repayment_profile']['weighted'])->toBe(5.0)
        ->and($result->score)->toBe(50.0)
        ->and($result->band)->toBe(SuitabilityBand::NeedsReview);
});

it('marks a scenario with no net benefit as not beneficial whatever its score', function () {
    $result = (new SuitabilityScorer)->score(scoredScenario(-1000, 1000000, 8, 0, 500000, 0), individualAffordability(30), ScoringConfig::defaults(ApplicantType::Individual), []);

    expect($result->band)->toBe(SuitabilityBand::NotBeneficial)
        ->and($result->score)->toBeGreaterThan(60.0)
        ->and($result->requiresAdvisorReview)->toBeTrue();
});

it('scores affordability zero when income is unknown', function () {
    $result = (new SuitabilityScorer)->score(scoredScenario(120000, 1000000, 8, 5000, 500000, 0), individualAffordability(null), ScoringConfig::defaults(ApplicantType::Individual), []);

    expect($result->dimensions['affordability']['score'])->toBe(0.0)
        ->and($result->score)->toBe(75.0);
});

it('sends a high-scoring case to an advisor when a review flag is raised', function () {
    $result = (new SuitabilityScorer)->score(
        scoredScenario(120000, 1000000, 8, 5000, 500000, 0),
        individualAffordability(35),
        ScoringConfig::defaults(ApplicantType::Individual),
        [new FlagRaised(RiskFlag::OverdueLiabilities)],
    );

    expect($result->band)->toBe(SuitabilityBand::PotentiallySuitable)
        ->and($result->requiresAdvisorReview)->toBeTrue();
});

it('rejects scoring weights that do not add up to 100', function () {
    new ScoringConfig(ApplicantType::Individual, 'bad', [
        'financial_benefit' => 30, 'affordability' => 30, 'interest_improvement' => 20, 'cost_efficiency' => 15, 'repayment_profile' => 10,
    ], []);
})->throws(InvalidArgumentException::class, 'Weights must add up to 100.');
