<?php

use App\Support\Calculators\FdCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Livewire;

it('mounts with sensible defaults and computes a maturity value', function () {
    Livewire::test('fixed-deposit-calculator')
        ->assertSet('principal', 100000.0)
        ->assertSet('annualRate', 7.00)
        ->assertSet('tenureYears', 5)
        ->assertOk();
});

it('recalculates the maturity value when the deposit amount changes', function () {
    Livewire::test('fixed-deposit-calculator')
        ->set('principal', 200000)
        ->assertSet('principal', 200000.0)
        ->assertOk();

    $result = FdCalculator::calculate(200000, 7.00, 60);
    expect($result['maturity_value'])->toBeGreaterThan(200000);
});

it('clamps a deposit amount typed above the allowed maximum', function () {
    Livewire::test('fixed-deposit-calculator')
        ->set('principal', 50_000_000)
        ->assertSet('principal', 10_000_000.0)
        ->assertHasErrors(['principal']);
});

it('clamps an interest rate typed below the allowed minimum', function () {
    Livewire::test('fixed-deposit-calculator')
        ->set('annualRate', 0.5)
        ->assertSet('annualRate', 3.00)
        ->assertHasErrors(['annualRate']);
});

it('shows the maturity value using Indian digit grouping', function () {
    $component = Livewire::test('fixed-deposit-calculator')->set('principal', 100000)->set('annualRate', 7)->set('tenureYears', 5);

    $result = FdCalculator::calculate(100000, 7, 60);
    $component->assertSee('₹'.IndianNumberFormatter::format($result['maturity_value']));
});
