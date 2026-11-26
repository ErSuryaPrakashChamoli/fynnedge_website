<?php

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\ForeclosureEstimate;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Services\Finance\ForeclosureEstimator;

function closableLoan(array $overrides = []): LiabilityInput
{
    return LiabilityInput::fromArray([
        'key' => 'pl',
        'type' => LiabilityType::PersonalLoan->value,
        'lender_name' => 'Bank A',
        'outstanding_principal' => 400000,
        'annual_interest_rate' => 16,
        'remaining_months' => 30,
        'overdue_amount' => 5000,
        'accrued_interest' => 1200,
        'foreclosure_charge_percent' => 4,
        'other_charges' => 500,
        ...$overrides,
    ]);
}

it('estimates a full closure as principal, dues, charge, GST on the charge and other charges', function () {
    $estimate = (new ForeclosureEstimator)->estimate(closableLoan(), new Assumptions('2026-11-25'));

    // 4,00,000 + 5,000 + 1,200 + 16,000 (4 %) + 2,880 (18 % GST) + 500.
    expect($estimate->total)->toBe(425580.0)
        ->and($estimate->charges)->toBe(16000.0)
        ->and($estimate->tax)->toBe(2880.0)
        ->and($estimate->closureCosts())->toBe(19380.0)
        ->and($estimate->source)->toBe(ForeclosureEstimate::SOURCE_ESTIMATE)
        ->and($estimate->verified)->toBeFalse();
});

it('charges a part-payment only on the amount paid down and leaves broken-period interest to the EMI', function () {
    $estimate = (new ForeclosureEstimator)->estimate(
        closableLoan(['mode' => ConsolidationMode::Partial->value, 'partial_amount' => 100000, 'other_charges' => 0]),
        new Assumptions('2026-11-25'),
    );

    // 1,00,000 + 5,000 overdue + 4,000 (4 %) + 720 GST.
    expect($estimate->isFullClosure)->toBeFalse()
        ->and($estimate->total)->toBe(109720.0)
        ->and($estimate->accruedInterest)->toBe(0.0);
});

it('uses a verified, current lender quotation instead of the estimate', function () {
    $estimate = (new ForeclosureEstimator)->estimate(
        closableLoan(['quoted_closure_amount' => 421000, 'quotation_valid_until' => '2026-12-10', 'quotation_verified' => true]),
        new Assumptions('2026-11-25'),
    );

    expect($estimate->total)->toBe(421000.0)
        ->and($estimate->source)->toBe(ForeclosureEstimate::SOURCE_QUOTATION)
        ->and($estimate->verified)->toBeTrue()
        ->and($estimate->closureCosts())->toBe(14800.0)
        ->and($estimate->flags)->toBe([]);
});

it('stops treating an expired quotation as verified', function () {
    $estimate = (new ForeclosureEstimator)->estimate(
        closableLoan(['quoted_closure_amount' => 421000, 'quotation_valid_until' => '2026-11-01', 'quotation_verified' => true]),
        new Assumptions('2026-11-25'),
    );

    expect($estimate->verified)->toBeFalse()
        ->and($estimate->quotationExpired)->toBeTrue()
        ->and($estimate->flags[0]->flag)->toBe(RiskFlag::QuotationExpired);
});

it('treats an unverified quotation as unverified', function () {
    $estimate = (new ForeclosureEstimator)->estimate(
        closableLoan(['quoted_closure_amount' => 421000, 'quotation_valid_until' => '2026-12-10']),
        new Assumptions('2026-11-25'),
    );

    expect($estimate->verified)->toBeFalse()
        ->and($estimate->flags[0]->flag)->toBe(RiskFlag::ForeclosureUnverified);
});

it('flags a foreclosure lock-in that has not ended', function () {
    $estimate = (new ForeclosureEstimator)->estimate(closableLoan(['lock_in_ends_on' => '2027-03-31']), new Assumptions('2026-11-25'));

    expect(array_map(fn ($flag) => $flag->flag, $estimate->flags))->toContain(RiskFlag::ForeclosureLockIn);
});

it('returns nothing for a liability that is kept', function () {
    expect((new ForeclosureEstimator)->estimate(closableLoan(['mode' => ConsolidationMode::Exclude->value]), new Assumptions('2026-11-25')))->toBeNull();
});
