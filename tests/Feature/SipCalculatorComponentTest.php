<?php

use Livewire\Livewire;

it('mounts with monthly defaults when no frequency is given', function () {
    Livewire::test('sip-calculator')
        ->assertSet('frequency', 'monthly')
        ->assertSet('installment', 5000.0)
        ->assertSet('tenureYears', 10)
        ->assertOk();
});

it('mounts with daily defaults when given the daily frequency', function () {
    Livewire::test('sip-calculator', ['frequency' => 'daily'])
        ->assertSet('frequency', 'daily')
        ->assertSet('installment', 100.0)
        ->assertSet('tenureYears', 3)
        ->assertOk();
});

it('falls back to monthly for an unrecognised frequency', function () {
    Livewire::test('sip-calculator', ['frequency' => 'weekly'])
        ->assertSet('frequency', 'monthly');
});

it('clamps a monthly installment typed above the allowed maximum', function () {
    Livewire::test('sip-calculator')
        ->set('installment', 5_000_000)
        ->assertSet('installment', 100000.0)
        ->assertHasErrors(['installment']);
});

it('clamps a daily installment typed above the allowed maximum', function () {
    Livewire::test('sip-calculator', ['frequency' => 'daily'])
        ->set('installment', 50000)
        ->assertSet('installment', 5000.0)
        ->assertHasErrors(['installment']);
});

it('produces a maturity value greater than the invested amount at a positive return rate', function () {
    $component = Livewire::test('sip-calculator')->set('annualRate', 12);

    expect($component->instance()->result()['maturity_value'])
        ->toBeGreaterThan($component->instance()->result()['invested_amount']);
});
