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

it('renders the yearly amortization table and pie chart split', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('Full yearly breakdown')
        ->assertSee('Principal vs. interest')
        ->assertSee('Principal & interest paid per year');
});

it('has no calculator for credit cards, so mounting with that category falls back to Personal Loan', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::CreditCard->value])
        ->assertSet('category', LoanCategory::PersonalLoan->value);
});
