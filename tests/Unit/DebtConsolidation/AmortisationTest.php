<?php

use App\Modules\DebtConsolidation\Services\Finance\Amortisation;
use App\Modules\DebtConsolidation\Services\Finance\Money;
use App\Support\Calculators\EmiCalculator;

it('calculates the standard reducing-balance EMI to the paisa', function () {
    // ₹5,00,000 at 18 % p.a. for 36 months: textbook EMI ₹18,076.20.
    expect(Amortisation::emi(500000, 18, 36))->toBe(18076.2);
});

it('agrees with the site EMI calculator', function (float $principal, float $rate, int $months) {
    expect(Amortisation::emi($principal, $rate, $months))->toBe(EmiCalculator::calculate($principal, $rate, $months)['emi']);
})->with([
    [500000, 18, 36],
    [661713, 12, 48],
    [2500000, 8.65, 240],
    [75000, 0, 12],
]);

it('splits a zero-rate loan into equal instalments', function () {
    expect(Amortisation::emi(120000, 0, 12))->toBe(10000.0);
});

it('rounds a half-paisa of interest up, as a lender does', function () {
    // ₹1,35,317 × 18 % ÷ 12 = ₹2,029.755 exactly — a float would hold 2029.7549999….
    expect(Amortisation::interest(135317, 18))->toBe(2029.76)
        ->and(Money::percentage(661713, 1.5))->toBe(9925.7);
});

it('clears the balance exactly with the last instalment', function () {
    $schedule = Amortisation::termSchedule(500000, 18, 18076.20, 36);

    expect($schedule->months())->toBe(36)
        ->and(Money::round($schedule->total() - $schedule->totalInterest()))->toBe(500000.0)
        // Independently computed (Decimal, half-up per month): 35 × 18,076.20 + 18,076.11.
        ->and($schedule->total())->toBe(650743.11)
        ->and($schedule->paymentAt(36))->toBe(18076.11);
});

it('finishes early when the payment is larger than needed', function () {
    // ₹1,50,000 card balance at 42 % p.a. paid ₹15,000 a month: cleared in month 13.
    $schedule = Amortisation::termSchedule(150000, 42, 15000, 480);

    expect($schedule->months())->toBe(13)
        ->and($schedule->total())->toBe(187897.96)
        ->and($schedule->interestAt(1))->toBe(5250.0);
});

it('settles a growing balance in the last month when the payment does not cover interest', function () {
    $schedule = Amortisation::termSchedule(100000, 36, 2000, 12);

    expect($schedule->months())->toBe(12)
        ->and(Amortisation::covers(100000, 36, 2000))->toBeFalse()
        ->and($schedule->paymentAt(12))->toBeGreaterThan(100000.0)
        ->and(Money::round($schedule->total() - $schedule->totalInterest()))->toBe(100000.0);
});

it('charges interest monthly and repays principal at the end of an interest-only facility', function () {
    $schedule = Amortisation::interestOnlySchedule(100000, 12, 6);

    expect($schedule->payments)->toBe([1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 101000.0])
        ->and($schedule->totalInterest())->toBe(6000.0);
});

it('recovers the annual rate implied by an EMI and remaining months', function () {
    expect(Amortisation::impliedAnnualRate(500000, 18076.20, 36))->toEqualWithDelta(18.0, 0.001);
});

it('reveals a flat rate as its higher reducing-balance equivalent', function () {
    // "10 % flat" on ₹3,00,000 for 3 years: EMI (3,00,000 + 90,000) ÷ 36 = ₹10,833.33 ≈ 17.92 % reducing (checked with an independent solver).
    expect(Amortisation::impliedAnnualRate(300000, 10833.33, 36))->toEqualWithDelta(17.92, 0.01);
});

it('returns no implied rate when the instalments do not cover the principal', function () {
    expect(Amortisation::impliedAnnualRate(500000, 10000, 36))->toBeNull();
});
