<?php

use App\Support\Calculators\FdCalculator;

it('matches simple compound interest for one year of annual compounding', function () {
    $result = FdCalculator::calculate(100000, 10, 12, compoundingsPerYear: 1);

    expect($result['maturity_value'])->toBe(110000.0)
        ->and($result['total_interest'])->toBe(10000.0);
});

it('produces a higher maturity value for more frequent compounding at the same rate', function () {
    $annual = FdCalculator::calculate(100000, 8, 24, compoundingsPerYear: 1);
    $quarterly = FdCalculator::calculate(100000, 8, 24, compoundingsPerYear: 4);

    expect($quarterly['maturity_value'])->toBeGreaterThan($annual['maturity_value']);
});

it('returns just the principal for a zero interest rate', function () {
    $result = FdCalculator::calculate(50000, 0, 12);

    expect($result['maturity_value'])->toBe(50000.0)
        ->and($result['total_interest'])->toBe(0.0);
});

it('returns zeros for a non-positive principal or tenure', function () {
    expect(FdCalculator::calculate(0, 7, 12))->toBe(['maturity_value' => 0.0, 'total_interest' => 0.0]);
    expect(FdCalculator::calculate(100000, 7, 0))->toBe(['maturity_value' => 0.0, 'total_interest' => 0.0]);
});

it('produces a higher maturity value for a longer tenure at the same rate', function () {
    $short = FdCalculator::calculate(100000, 7, 12);
    $long = FdCalculator::calculate(100000, 7, 60);

    expect($long['maturity_value'])->toBeGreaterThan($short['maturity_value']);
});
