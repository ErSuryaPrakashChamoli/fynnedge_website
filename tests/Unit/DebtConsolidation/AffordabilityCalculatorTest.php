<?php

use App\Modules\DebtConsolidation\DataTransferObjects\BusinessFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\AffordabilityBand;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Services\Finance\AffordabilityCalculator;

it('calculates FOIR before and after on total income, including other fixed obligations', function () {
    $finances = new IndividualFinances(netMonthlyIncome: 90000, otherMonthlyIncome: 10000, monthlyLivingExpenses: 30000, otherMonthlyObligations: 2000);

    $result = (new AffordabilityCalculator)->forIndividual($finances, 48000, 28000, ScoringConfig::defaults(ApplicantType::Individual));

    // (48,000 + 2,000) ÷ 1,00,000 = 50 %; (28,000 + 2,000) ÷ 1,00,000 = 30 %.
    expect($result->currentValue)->toBe(50.0)
        ->and($result->proposedValue)->toBe(30.0)
        ->and($result->currentBand)->toBe(AffordabilityBand::Manageable)
        ->and($result->proposedBand)->toBe(AffordabilityBand::Comfortable)
        ->and($result->disposableBefore)->toBe(20000.0)
        ->and($result->disposableAfter)->toBe(40000.0)
        ->and($result->flags)->toBe([]);
});

it('flags a proposed FOIR in the stretched band', function () {
    $result = (new AffordabilityCalculator)->forIndividual(new IndividualFinances(100000), 70000, 60000, ScoringConfig::defaults(ApplicantType::Individual));

    expect($result->proposedBand)->toBe(AffordabilityBand::Stretched)
        ->and($result->flags[0]->flag)->toBe(RiskFlag::HighFoir);
});

it('reports affordability as unknown without income', function () {
    $result = (new AffordabilityCalculator)->forIndividual(new IndividualFinances(null), 20000, 15000, ScoringConfig::defaults(ApplicantType::Individual));

    expect($result->proposedBand)->toBe(AffordabilityBand::Unknown)
        ->and($result->proposedValue)->toBeNull()
        ->and($result->flags[0]->flag)->toBe(RiskFlag::MissingInformation);
});

it('calculates business debt-service cover from revenue less operating expenses', function () {
    $finances = new BusinessFinances(averageMonthlyRevenue: 500000, monthlyOperatingExpenses: 380000);

    $result = (new AffordabilityCalculator)->forBusiness($finances, 100000, 60000, ScoringConfig::defaults(ApplicantType::Business));

    // 1,20,000 available: 1.2× today, 2.0× after.
    expect($result->currentValue)->toBe(1.2)
        ->and($result->proposedValue)->toBe(2.0)
        ->and($result->currentBand)->toBe(AffordabilityBand::Stretched)
        ->and($result->proposedBand)->toBe(AffordabilityBand::Comfortable)
        ->and($result->disposableAfter)->toBe(60000.0);
});

it('flags cash flow that does not cover the proposed repayments', function () {
    $result = (new AffordabilityCalculator)->forBusiness(new BusinessFinances(300000, 250000), 40000, 55000, ScoringConfig::defaults(ApplicantType::Business));

    expect($result->proposedValue)->toBe(0.91)
        ->and($result->proposedBand)->toBe(AffordabilityBand::Unaffordable)
        ->and($result->flags[0]->flag)->toBe(RiskFlag::LowDebtServiceCover);
});
