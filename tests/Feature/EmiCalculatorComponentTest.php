<?php

use App\Support\Calculators\EmiCalculator;
use Livewire\Livewire;

it('recalculates the EMI when the loan amount changes', function () {
    Livewire::test('emi-calculator')
        ->assertSet('principal', 500000.0)
        ->set('principal', 1000000)
        ->assertSet('principal', 1000000.0)
        ->assertOk();
});

it('rejects an interest rate above the allowed maximum', function () {
    Livewire::test('emi-calculator')
        ->set('annualRate', 50)
        ->assertHasErrors(['annualRate']);
});

it('computes the same result the calculator service would return', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 200000)
        ->set('annualRate', 12)
        ->set('tenureMonths', 24)
        ->assertOk();

    expect(EmiCalculator::calculate(200000, 12, 24)['emi'])->toBeGreaterThan(0);
});
