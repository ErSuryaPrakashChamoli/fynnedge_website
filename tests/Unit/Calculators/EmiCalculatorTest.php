<?php

use App\Support\Calculators\EmiCalculator;

it('matches principal divided by tenure when the rate is zero', function () {
    $result = EmiCalculator::calculate(principal: 120000, annualRatePercent: 0, tenureMonths: 12);

    expect($result['emi'])->toBe(10000.0)
        ->and($result['total_payment'])->toBe(120000.0)
        ->and($result['total_interest'])->toBe(0.0);
});

it('charges interest on top of principal when the rate is positive', function () {
    $result = EmiCalculator::calculate(principal: 500000, annualRatePercent: 10.5, tenureMonths: 60);

    expect($result['emi'])->toBeGreaterThan(500000 / 60)
        ->and($result['total_payment'])->toBeGreaterThan(500000)
        ->and($result['total_interest'])->toBe(round($result['total_payment'] - 500000, 2))
        ->and(round($result['emi'] * 60, 0))->toBe(round($result['total_payment'], 0));
});

it('produces a higher EMI for a shorter tenure at the same rate', function () {
    $short = EmiCalculator::calculate(500000, 10.5, 24);
    $long = EmiCalculator::calculate(500000, 10.5, 60);

    expect($short['emi'])->toBeGreaterThan($long['emi']);
});

it('produces a higher EMI for a higher rate at the same tenure', function () {
    $lowRate = EmiCalculator::calculate(500000, 8, 36);
    $highRate = EmiCalculator::calculate(500000, 16, 36);

    expect($highRate['emi'])->toBeGreaterThan($lowRate['emi']);
});

it('returns zeros for a non-positive principal or tenure', function () {
    expect(EmiCalculator::calculate(0, 10, 12))->toBe(['emi' => 0.0, 'total_payment' => 0.0, 'total_interest' => 0.0]);
    expect(EmiCalculator::calculate(100000, 10, 0))->toBe(['emi' => 0.0, 'total_payment' => 0.0, 'total_interest' => 0.0]);
});

it('produces one yearly schedule row per full year of tenure', function () {
    $schedule = EmiCalculator::yearlySchedule(500000, 10.5, 60);

    expect($schedule)->toHaveCount(5);
    expect(array_column($schedule, 'year'))->toBe([1, 2, 3, 4, 5]);
});

it('gives the final year only the leftover months instead of padding to a whole year', function () {
    $schedule = EmiCalculator::yearlySchedule(500000, 10.5, 66);

    expect($schedule)->toHaveCount(6);
    // A 6-month final year pays roughly half a full year's principal+interest — not zero, not a full year's worth.
    $fullYear = $schedule[0]['principal_paid'] + $schedule[0]['interest_paid'];
    $finalYear = $schedule[5]['principal_paid'] + $schedule[5]['interest_paid'];
    expect($finalYear)->toBeGreaterThan(0)->toBeLessThan($fullYear);
});

it('reduces the outstanding balance to zero by the final year', function () {
    $schedule = EmiCalculator::yearlySchedule(500000, 10.5, 36);

    expect(end($schedule)['balance'])->toBe(0.0);
});

it('sums the yearly schedule back to the same totals calculate() reports', function () {
    $totals = EmiCalculator::calculate(500000, 10.5, 60);
    $schedule = EmiCalculator::yearlySchedule(500000, 10.5, 60);

    $totalPrincipal = round(array_sum(array_column($schedule, 'principal_paid')), 0);
    $totalInterest = round(array_sum(array_column($schedule, 'interest_paid')), 0);

    expect($totalPrincipal)->toBe(500000.0);
    expect($totalInterest)->toBe(round($totals['total_interest'], 0));
});

it('pays down more principal and less interest in later years, as a reducing-balance loan should', function () {
    $schedule = EmiCalculator::yearlySchedule(2000000, 9, 240);

    expect($schedule[0]['interest_paid'])->toBeGreaterThan($schedule[0]['principal_paid']);
    expect(end($schedule)['principal_paid'])->toBeGreaterThan(end($schedule)['interest_paid']);
});

it('returns an empty schedule for a non-positive principal or tenure', function () {
    expect(EmiCalculator::yearlySchedule(0, 10, 12))->toBe([]);
    expect(EmiCalculator::yearlySchedule(100000, 10, 0))->toBe([]);
});
