<?php

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Services\Finance\LiabilityProjector;
use App\Modules\DebtConsolidation\Services\Finance\PortfolioAnalyser;

it('summarises exposure, weighted rate, security mix, concentration and maturity', function () {
    $liabilities = [
        new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 300000, 16, remainingMonths: 24),
        new LiabilityInput('car', LiabilityType::VehicleLoan, 'Bank A', 500000, 10, remainingMonths: 48),
        new LiabilityInput('cc', LiabilityType::CreditCardOutstanding, 'Card C', 200000, 40, monthlyPayment: 20000, overdueAmount: 4000, daysPastDue: 20),
    ];
    $projections = [];

    foreach ($liabilities as $liability) {
        $projections[$liability->key] = (new LiabilityProjector)->project($liability, 60, new Assumptions('2026-11-25'));
    }

    $summary = (new PortfolioAnalyser)->summarise($liabilities, $projections);

    // Weighted rate: (3 L × 16 + 5 L × 10 + 2 L × 40) ÷ 10 L = 17.8 %.
    expect($summary->liabilityCount)->toBe(3)
        ->and($summary->totalOutstanding)->toBe(1000000.0)
        ->and($summary->weightedAverageRate)->toBe(17.8)
        ->and($summary->highestRate)->toBe(40.0)
        ->and($summary->securedOutstanding)->toBe(500000.0)
        ->and($summary->securedShare)->toBe(50.0)
        ->and($summary->totalOverdue)->toBe(4000.0)
        ->and($summary->liabilitiesInArrears)->toBe(1)
        // Bank A holds 80 %, Card C 20 %: HHI = 80² + 20² = 6,800.
        ->and($summary->largestLenderShare)->toBe(80.0)
        ->and($summary->concentrationIndex)->toBe(6800.0)
        // The card clears in 13 months at ₹20,000 (−ln(1 − 2 L × 3.33 % ÷ 20,000) ÷ ln 1.0333 = 12.4).
        ->and($summary->maturityProfile['up_to_12']['count'])->toBe(0)
        ->and($summary->maturityProfile['13_to_36'])->toBe(['count' => 2, 'outstanding' => 500000.0])
        ->and($summary->maturityProfile['37_to_60']['outstanding'])->toBe(500000.0);
});

it('returns an empty summary for no liabilities', function () {
    $summary = (new PortfolioAnalyser)->summarise([], []);

    expect($summary->totalOutstanding)->toBe(0.0)
        ->and($summary->weightedAverageRate)->toBe(0.0)
        ->and($summary->concentrationIndex)->toBeNull();
});
