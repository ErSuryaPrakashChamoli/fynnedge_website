<?php

use App\Support\Calculators\PrepaymentCalculator;

it('shortens the tenure while keeping the same EMI in reduce_tenure mode', function () {
    $result = PrepaymentCalculator::calculate(
        outstandingPrincipal: 1000000,
        annualRatePercent: 10,
        remainingTenureMonths: 60,
        prepaymentAmount: 200000,
        mode: 'reduce_tenure',
    );

    expect($result['new_emi'])->toBe($result['original_emi']);
    expect($result['new_tenure_months'])->toBeLessThan($result['original_tenure_months']);
    expect($result['tenure_reduced_months'])->toBe($result['original_tenure_months'] - $result['new_tenure_months']);
    expect($result['interest_saved'])->toBeGreaterThan(0);
});

it('lowers the EMI while keeping the same tenure in reduce_emi mode', function () {
    $result = PrepaymentCalculator::calculate(
        outstandingPrincipal: 1000000,
        annualRatePercent: 10,
        remainingTenureMonths: 60,
        prepaymentAmount: 200000,
        mode: 'reduce_emi',
    );

    expect($result['new_tenure_months'])->toBe($result['original_tenure_months']);
    expect($result['new_emi'])->toBeLessThan($result['original_emi']);
    expect($result['interest_saved'])->toBeGreaterThan(0);
});

it('clears the loan entirely when the prepayment covers the full outstanding principal', function () {
    $result = PrepaymentCalculator::calculate(
        outstandingPrincipal: 500000,
        annualRatePercent: 10,
        remainingTenureMonths: 36,
        prepaymentAmount: 500000,
        mode: 'reduce_tenure',
    );

    expect($result['new_tenure_months'])->toBe(0);
    expect($result['new_emi'])->toBe(0.0);
    expect($result['interest_saved'])->toBe($result['original_total_interest']);
});

it('clamps a prepayment amount larger than the outstanding principal', function () {
    $result = PrepaymentCalculator::calculate(
        outstandingPrincipal: 500000,
        annualRatePercent: 10,
        remainingTenureMonths: 36,
        prepaymentAmount: 5_000_000,
        mode: 'reduce_tenure',
    );

    expect($result['new_tenure_months'])->toBe(0);
});

it('saves more interest for a larger prepayment amount', function () {
    $small = PrepaymentCalculator::calculate(500000, 10, 60, 50000, 'reduce_tenure');
    $large = PrepaymentCalculator::calculate(500000, 10, 60, 200000, 'reduce_tenure');

    expect($large['interest_saved'])->toBeGreaterThan($small['interest_saved']);
});

it('returns zeros for a non-positive outstanding principal or tenure', function () {
    $zeroPrincipal = PrepaymentCalculator::calculate(0, 10, 60, 10000, 'reduce_tenure');
    $zeroTenure = PrepaymentCalculator::calculate(500000, 10, 0, 10000, 'reduce_tenure');

    expect($zeroPrincipal['original_emi'])->toBe(0.0);
    expect($zeroTenure['original_emi'])->toBe(0.0);
});
