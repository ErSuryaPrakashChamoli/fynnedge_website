<?php

use App\Support\Calculators\EmiCalculator;

it('charges interest-only on the full principal during the initial tenure', function () {
    $result = EmiCalculator::calculateHybrid(principal: 1200000, annualRatePercent: 12, initialTenureMonths: 12, subsequentTenureMonths: 48);

    // 12% p.a. on 12,00,000 = 1% per month = 12,000/month, interest-only.
    expect($result['initial_emi'])->toBe(12000.0);
});

it('matches the standard reducing-balance EMI for the subsequent stage', function () {
    $result = EmiCalculator::calculateHybrid(principal: 500000, annualRatePercent: 10.5, initialTenureMonths: 12, subsequentTenureMonths: 48);
    $standalone = EmiCalculator::calculate(500000, 10.5, 48);

    expect($result['subsequent_emi'])->toBe($standalone['emi']);
});

it('reports a total tenure equal to initial plus subsequent tenure', function () {
    $result = EmiCalculator::calculateHybrid(1000000, 10, 24, 36);

    expect($result['total_tenure_months'])->toBe(60);
});

it('produces a total repayment equal to principal plus total interest', function () {
    $result = EmiCalculator::calculateHybrid(1000000, 10, 12, 48);

    expect($result['total_repayment'])->toBe(round(1000000 + $result['total_interest'], 2));
});

it('produces a higher initial EMI for a shorter initial tenure at the same rate, since it is interest-only either way', function () {
    // Interest-only EMI depends only on principal and rate, not on how long the
    // initial tenure lasts — this pins that invariant so a future change can't
    // accidentally make initial_emi vary with initial tenure length.
    $short = EmiCalculator::calculateHybrid(1000000, 12, 6, 54);
    $long = EmiCalculator::calculateHybrid(1000000, 12, 24, 36);

    expect($short['initial_emi'])->toBe($long['initial_emi']);
});

it('throws when the principal is not positive', function () {
    EmiCalculator::calculateHybrid(0, 10, 12, 48);
})->throws(InvalidArgumentException::class);

it('throws when the initial tenure is not positive', function () {
    EmiCalculator::calculateHybrid(500000, 10, 0, 48);
})->throws(InvalidArgumentException::class);

it('throws when the subsequent tenure is not positive', function () {
    EmiCalculator::calculateHybrid(500000, 10, 60, 0);
})->throws(InvalidArgumentException::class);

it('produces one interest-only month per initial-tenure month, with the balance unchanged', function () {
    $schedule = EmiCalculator::hybridMonthlySchedule(1200000, 12, 12, 48);
    $initialMonths = array_filter($schedule, fn (array $row) => $row['stage'] === 'initial');

    expect($initialMonths)->toHaveCount(12);
    foreach ($initialMonths as $row) {
        expect($row['principal_paid'])->toBe(0.0)
            ->and($row['interest_paid'])->toBe(12000.0)
            ->and($row['balance'])->toBe(1200000.0);
    }
});

it('numbers subsequent-stage months absolutely, continuing on from the initial tenure', function () {
    $schedule = EmiCalculator::hybridMonthlySchedule(500000, 10.5, 12, 48);
    $subsequentMonths = array_values(array_filter($schedule, fn (array $row) => $row['stage'] === 'subsequent'));

    expect($schedule)->toHaveCount(60);
    expect($subsequentMonths[0])->toMatchArray(['month' => 13, 'year' => 2, 'month_in_year' => 1]);
    expect(end($subsequentMonths)['month'])->toBe(60);
});

it('reduces the balance to zero only by the end of the subsequent stage', function () {
    $schedule = EmiCalculator::hybridMonthlySchedule(500000, 10.5, 12, 48);

    expect(end($schedule)['balance'])->toBe(0.0);
});

it('aggregates the hybrid monthly schedule into one yearly row per initial-tenure-spanning year plus subsequent years', function () {
    $yearly = EmiCalculator::hybridYearlySchedule(500000, 10.5, 12, 48);

    expect($yearly)->toHaveCount(5);
    expect(array_column($yearly, 'year'))->toBe([1, 2, 3, 4, 5]);
    expect(end($yearly)['balance'])->toBe(0.0);
});

it('returns an empty hybrid schedule for a non-positive principal or tenure', function () {
    expect(EmiCalculator::hybridMonthlySchedule(0, 10, 12, 48))->toBe([]);
    expect(EmiCalculator::hybridMonthlySchedule(500000, 10, 0, 48))->toBe([]);
    expect(EmiCalculator::hybridMonthlySchedule(500000, 10, 12, 0))->toBe([]);
});
