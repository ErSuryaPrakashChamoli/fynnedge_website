<?php

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Services\Finance\LiabilityProjector;

function projectorFlags(array $flags): array
{
    return array_map(fn (FlagRaised $flag): string => $flag->flag->value, $flags);
}

it('uses the contractual EMI and remaining months when both are known', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, monthlyPayment: 18076.20, remainingMonths: 36);

    $projection = (new LiabilityProjector)->project($loan, 60, new Assumptions('2026-11-25'));

    expect($projection->schedule->months())->toBe(36)
        ->and($projection->schedule->total())->toBe(650743.11)
        ->and($projection->monthlyObligation)->toBe(18076.2)
        ->and($projection->flags)->toBe([]);
});

it('flags a stated flat rate that does not match the EMI and keeps the EMI', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 300000, 10, monthlyPayment: 10833.33, remainingMonths: 36);

    $projection = (new LiabilityProjector)->project($loan, 60, new Assumptions('2026-11-25'));

    expect(projectorFlags($projection->flags))->toBe([RiskFlag::RateMismatch->value])
        ->and($projection->effectiveAnnualRate)->toEqualWithDelta(17.92, 0.01)
        ->and($projection->schedule->total())->toEqualWithDelta(390000.0, 1.0);
});

it('flags EMI and months that cannot repay the outstanding', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, monthlyPayment: 10000, remainingMonths: 36);

    $projection = (new LiabilityProjector)->project($loan, 60, new Assumptions('2026-11-25'));

    expect(projectorFlags($projection->flags))->toContain(RiskFlag::InconsistentLoanDetails->value);
});

it('derives the EMI from the rate and months when no EMI is given', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18, remainingMonths: 36);

    $projection = (new LiabilityProjector)->project($loan, 60, new Assumptions('2026-11-25'));

    expect($projection->monthlyObligation)->toBe(18076.2)
        ->and($projection->schedule->total())->toBe(650743.11)
        ->and($projection->assumed)->toBeFalse();
});

it('assumes the fallback tenure and says so when neither EMI nor months are known', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 500000, 18);

    $projection = (new LiabilityProjector)->project($loan, 48, new Assumptions('2026-11-25'));

    expect($projection->schedule->months())->toBe(48)
        ->and($projection->assumed)->toBeTrue()
        ->and(projectorFlags($projection->flags))->toBe([RiskFlag::AssumedRepayment->value]);
});

it('uses the minimum payment percentage as the card obligation when no payment is planned', function () {
    $card = new LiabilityInput('cc', LiabilityType::CreditCardOutstanding, 'Card', 200000, 42);

    $projection = (new LiabilityProjector)->project($card, 36, new Assumptions('2026-11-25', revolvingMinimumPaymentPercent: 5));

    expect($projection->monthlyObligation)->toBe(10000.0)
        ->and($projection->schedule->months())->toBe(36)
        ->and(projectorFlags($projection->flags))->toBe([RiskFlag::AssumedRepayment->value]);
});

it('flags a card payment that never reduces the balance', function () {
    // 3.5 % a month on ₹2,00,000 is ₹7,000 of interest; paying ₹5,000 lets the debt grow.
    $card = new LiabilityInput('cc', LiabilityType::CreditCardOutstanding, 'Card', 200000, 42, monthlyPayment: 5000);

    $projection = (new LiabilityProjector)->project($card, 24, new Assumptions('2026-11-25'));

    expect(projectorFlags($projection->flags))->toBe([RiskFlag::PaymentBelowInterest->value])
        ->and($projection->schedule->months())->toBe(24)
        ->and($projection->schedule->paymentAt(24))->toBeGreaterThan(200000.0);
});

it('keeps the EMI and shortens the term when part of a loan is paid down', function () {
    $loan = new LiabilityInput('pl', LiabilityType::PersonalLoan, 'Bank A', 400000, 15, remainingMonths: 48);

    $projection = (new LiabilityProjector)->project($loan, 48, new Assumptions('2026-11-25'), principal: 300000);

    // Independently computed: ₹3,00,000 at 15 % with the original ₹11,132.30 EMI ends in 34 months.
    expect($projection->monthlyObligation)->toBe(11132.3)
        ->and($projection->schedule->months())->toBe(34)
        ->and($projection->schedule->total())->toBe(368107.14);
});

it('projects interest-only facilities as interest each month and principal at the end', function () {
    $cashCredit = new LiabilityInput('cc', LiabilityType::CashCredit, 'Bank', 1000000, 12, remainingMonths: 12, repaymentStructure: RepaymentStructure::InterestOnly);

    $projection = (new LiabilityProjector)->project($cashCredit, 60, new Assumptions('2026-11-25'));

    expect($projection->monthlyObligation)->toBe(10000.0)
        ->and($projection->schedule->total())->toBe(1120000.0)
        ->and($projection->schedule->months())->toBe(12);
});
