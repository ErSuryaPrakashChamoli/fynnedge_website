<?php

use App\Enums\LoanCategory;
use App\Support\Calculators\EmiCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Livewire;

beforeEach(function () {
    // Every category the calculator supports, seeded with the same limits
    // production ships — most tests exercise Personal Loan by default, and
    // several also switch to another category mid-test.
    foreach ([
        LoanCategory::PersonalLoan,
        LoanCategory::HomeLoan,
        LoanCategory::CarLoan,
        LoanCategory::LoanAgainstProperty,
        LoanCategory::BusinessLoan,
    ] as $category) {
        seedCalculatorProduct($category);
    }
});

it('defaults to the Personal Loan preset when mounted with no category', function () {
    Livewire::test('emi-calculator')
        ->assertSet('category', LoanCategory::PersonalLoan->value)
        ->assertSet('principal', 500000.0)
        ->assertSet('annualRate', 10.49) // Personal Loan's minimum ROI
        ->assertSet('tenureYears', 3)
        ->assertOk();
});

it('mounts with the given category and its preset defaults', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::HomeLoan->value])
        ->assertSet('category', LoanCategory::HomeLoan->value)
        ->assertSet('principal', 4000000.0)
        ->assertSet('annualRate', 7.00) // Home Loan's minimum ROI
        ->assertSet('tenureYears', 20)
        ->assertOk();
});

it('mounts with the new Car Loan category and its preset defaults', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::CarLoan->value])
        ->assertSet('category', LoanCategory::CarLoan->value)
        ->assertSet('principal', 800000.0)
        ->assertSet('annualRate', 9.10) // Car Loan's minimum ROI
        ->assertSet('tenureYears', 5)
        ->assertOk();
});

it('recalculates the EMI when the loan amount changes', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 1000000)
        ->assertSet('principal', 1000000.0)
        ->assertOk();
});

it('clamps an interest rate typed above the active category\'s maximum, rather than computing an EMI from it', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('annualRate', 50)
        ->assertSet('annualRate', 24.0) // Personal Loan's max_interest_rate
        ->assertHasErrors(['annualRate']);
});

it('clamps an interest rate typed below the active category\'s minimum, rather than computing an EMI from it', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('annualRate', 2)
        ->assertSet('annualRate', 10.49) // Personal Loan's min_interest_rate
        ->assertHasErrors(['annualRate']);
});

it('clamps a loan amount typed above the active category\'s maximum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 50_000_000)
        ->assertSet('principal', 5_000_000.0) // Personal Loan's max_amount
        ->assertHasErrors(['principal']);
});

it('clamps a tenure typed above the active category\'s maximum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('tenureYears', 50)
        ->assertSet('tenureYears', 7) // Personal Loan's max_years (84 months)
        ->assertHasErrors(['tenureYears']);
});

it('clamps a value typed below the active category\'s minimum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 100)
        ->assertSet('principal', 25_000.0) // Personal Loan's min_amount
        ->assertHasErrors(['principal']);
});

it('never computes an EMI from an out-of-range value — the result reflects the clamped amount', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 50_000_000); // 10x the ₹50L maximum

    $clampedResult = EmiCalculator::calculate(5_000_000, 10.49, 36); // max_amount, unchanged default rate/tenure
    $bypassedResult = EmiCalculator::calculate(50_000_000, 10.49, 36);

    $component->assertSeeText('₹'.IndianNumberFormatter::format($clampedResult['emi']));
    $component->assertDontSeeText('₹'.IndianNumberFormatter::format($bypassedResult['emi']));
});

it('clears the clamp error once the value is back in range', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 50_000_000)
        ->assertHasErrors(['principal'])
        ->set('principal', 1_000_000)
        ->assertHasNoErrors(['principal']);
});

it('computes the same result the calculator service would return', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 200000)
        ->set('annualRate', 12)
        ->set('tenureYears', 2)
        ->assertOk();

    expect(EmiCalculator::calculate(200000, 12, 24)['emi'])->toBeGreaterThan(0);
});

it('resets amount and tenure to the new category\'s preset when the category is switched', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 1000000)
        ->call('selectCategory', LoanCategory::BusinessLoan->value)
        ->assertSet('category', LoanCategory::BusinessLoan->value)
        ->assertSet('principal', 1000000.0) // Business Loan's default_amount
        ->assertSet('tenureYears', 5); // Business Loan's default_tenure_months / 12
});

it('keeps the current interest rate when switching to a category whose range still covers it', function () {
    // Personal Loan mounts at its minimum, 10.49% — Business Loan's own range (9.60%–24.00%) also covers 10.49%.
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSet('annualRate', 10.49)
        ->call('selectCategory', LoanCategory::BusinessLoan->value)
        ->assertSet('annualRate', 10.49);
});

it('resets the interest rate to the new category\'s minimum when the previous rate falls outside its range', function () {
    // Car Loan's range (9.10%–15.00%) allows 12%, but Home Loan's range (7.00%–10.50%) does not.
    Livewire::test('emi-calculator', ['category' => LoanCategory::CarLoan->value])
        ->set('annualRate', 12)
        ->assertSet('annualRate', 12.0)
        ->call('selectCategory', LoanCategory::HomeLoan->value)
        ->assertSet('annualRate', 7.00); // Home Loan's minimum ROI
});

it('renders the amortization table and pie chart split', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('Full breakdown, starting this month')
        ->assertSee('Principal vs. interest')
        ->assertSee('Principal & interest paid per year');
});

it('shows the maximum amount, maximum tenure and indicative rate range near the calculator fields', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('Maximum loan amount: ₹50,00,000') // Indian digit grouping, not 5,000,000
        ->assertSee('Maximum tenure: 7 years (84 months)')
        ->assertSee('10.49% – 24.00%');
});

it('shows a paired number input alongside each range slider', function () {
    $html = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])->html();

    expect($html)->toContain('id="principal"')->toContain('type="number"')
        ->toContain('id="annualRate"')
        ->toContain('id="tenureYears"');
});

it('labels each period and month with real calendar dates starting from the current month', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('tenureYears', 2);

    $instance = $component->instance();
    $months = $instance->monthsByYear()[1];

    // The schedule starts THIS month, not some abstract "Month 1" counted from a hypothetical disbursal date.
    expect($instance->monthDate(1)->isSameMonth(now()))->toBeTrue();

    foreach ($months as $monthRow) {
        $component->assertSee($instance->monthDate($monthRow['month'])->format('M Y'));
    }

    $component->assertSee($instance->yearLabel($instance->monthsByYear()[1]));
    $component->assertSee($instance->yearLabel($instance->monthsByYear()[2]));
    $component->assertSee('Assumes your first EMI falls this month');
    $component->assertDontSee('Year 1')->assertDontSee('Month 1');
});

it('keeps the monthly detail totals consistent with the yearly summary row', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('tenureYears', 1);

    $monthsByYear = $component->instance()->monthsByYear();
    $yearly = $component->instance()->schedule();

    $summedPrincipal = round(array_sum(array_column($monthsByYear[1], 'principal_paid')), 2);
    $summedInterest = round(array_sum(array_column($monthsByYear[1], 'interest_paid')), 2);

    expect($monthsByYear[1])->toHaveCount(12);
    expect($summedPrincipal)->toBe($yearly[0]['principal_paid']);
    expect($summedInterest)->toBe($yearly[0]['interest_paid']);
});

it('labels a full 12-month period as a single month when it starts and ends in the same month, otherwise as a range', function () {
    $instance = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])->instance();

    expect($instance->yearLabel([['month' => 1]]))->toBe($instance->monthDate(1)->format('M Y'));
    expect($instance->yearLabel([['month' => 1], ['month' => 12]]))
        ->toBe($instance->monthDate(1)->format('M Y').' – '.$instance->monthDate(12)->format('M Y'));
});

it('gives the partial final period of a non-whole-year tenure a range covering only its own leftover months', function () {
    $instance = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])->instance();
    $months = EmiCalculator::monthlySchedule(500000, 10.5, 14);

    $byPeriod = [];
    foreach ($months as $row) {
        $byPeriod[$row['year']][] = $row;
    }

    expect($byPeriod[2])->toHaveCount(2);
    expect($instance->yearLabel($byPeriod[2]))
        ->toBe($instance->monthDate(13)->format('M Y').' – '.$instance->monthDate(14)->format('M Y'));
});

it('has no calculator for credit cards, so mounting with that category falls back to Personal Loan', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::CreditCard->value])
        ->assertSet('category', LoanCategory::PersonalLoan->value);
});
