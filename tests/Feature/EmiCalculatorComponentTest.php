<?php

use App\Enums\LoanCategory;
use App\Support\Calculators\EmiCalculator;
use Livewire\Livewire;

it('defaults to the Personal Loan preset when mounted with no category', function () {
    Livewire::test('emi-calculator')
        ->assertSet('category', LoanCategory::PersonalLoan->value)
        ->assertSet('principal', 500000.0)
        ->assertSet('annualRate', 13.5)
        ->assertSet('tenureYears', 3)
        ->assertOk();
});

it('mounts with the given category and its preset defaults', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::HomeLoan->value])
        ->assertSet('category', LoanCategory::HomeLoan->value)
        ->assertSet('principal', 4000000.0)
        ->assertSet('annualRate', 8.9)
        ->assertSet('tenureYears', 20)
        ->assertOk();
});

it('recalculates the EMI when the loan amount changes', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 1000000)
        ->assertSet('principal', 1000000.0)
        ->assertOk();
});

it('rejects an interest rate above the active category\'s allowed maximum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('annualRate', 50)
        ->assertHasErrors(['annualRate']);
});

it('computes the same result the calculator service would return', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 200000)
        ->set('annualRate', 12)
        ->set('tenureYears', 2)
        ->assertOk();

    expect(EmiCalculator::calculate(200000, 12, 24)['emi'])->toBeGreaterThan(0);
});

it('resets amount, rate and tenure to the new category\'s preset when the category is switched', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 1000000)
        ->call('selectCategory', LoanCategory::BusinessLoan->value)
        ->assertSet('category', LoanCategory::BusinessLoan->value)
        ->assertSet('principal', 1000000.0)
        ->assertSet('annualRate', 15.0)
        ->assertSet('tenureYears', 5);
});

it('renders the amortization table and pie chart split', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('Full breakdown, starting this month')
        ->assertSee('Principal vs. interest')
        ->assertSee('Principal & interest paid per year');
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
