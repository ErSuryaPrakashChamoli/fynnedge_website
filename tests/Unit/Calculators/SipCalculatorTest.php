<?php

use App\Support\Calculators\SipCalculator;

it('sums the plain installments for a monthly SIP at zero return', function () {
    $result = SipCalculator::monthly(5000, 0, 12);

    expect($result['invested_amount'])->toBe(60000.0)
        ->and($result['estimated_returns'])->toBe(0.0)
        ->and($result['maturity_value'])->toBe(60000.0);
});

it('produces a maturity value greater than the invested amount at a positive return rate', function () {
    $result = SipCalculator::monthly(5000, 12, 60);

    expect($result['maturity_value'])->toBeGreaterThan($result['invested_amount']);
    expect($result['estimated_returns'])->toBe(round($result['maturity_value'] - $result['invested_amount'], 2));
});

it('produces a higher maturity value for a longer tenure at the same rate', function () {
    $short = SipCalculator::monthly(5000, 12, 24);
    $long = SipCalculator::monthly(5000, 12, 60);

    expect($long['maturity_value'])->toBeGreaterThan($short['maturity_value']);
});

it('sums the plain installments for a daily SIP at zero return', function () {
    $result = SipCalculator::daily(100, 0, 365);

    expect($result['invested_amount'])->toBe(36500.0)
        ->and($result['maturity_value'])->toBe(36500.0);
});

it('produces a maturity value greater than the invested amount for a daily SIP at a positive rate', function () {
    $result = SipCalculator::daily(100, 10, 365);

    expect($result['maturity_value'])->toBeGreaterThan($result['invested_amount']);
});

it('returns zeros for a non-positive installment or period count', function () {
    expect(SipCalculator::monthly(0, 12, 12))->toBe(['invested_amount' => 0.0, 'estimated_returns' => 0.0, 'maturity_value' => 0.0]);
    expect(SipCalculator::monthly(5000, 12, 0))->toBe(['invested_amount' => 0.0, 'estimated_returns' => 0.0, 'maturity_value' => 0.0]);
    expect(SipCalculator::daily(0, 12, 365))->toBe(['invested_amount' => 0.0, 'estimated_returns' => 0.0, 'maturity_value' => 0.0]);
});
