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
