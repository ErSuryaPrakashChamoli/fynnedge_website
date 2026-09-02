<?php

use App\Support\Calculators\GstCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Livewire;

it('mounts with sensible defaults in add-GST mode', function () {
    Livewire::test('gst-calculator')
        ->assertSet('amount', 10000.0)
        ->assertSet('rate', 18.00)
        ->assertSet('mode', 'add')
        ->assertOk();
});

it('switches to remove-GST mode', function () {
    Livewire::test('gst-calculator')
        ->call('selectMode', 'remove')
        ->assertSet('mode', 'remove');
});

it('shows the total amount including GST in add mode', function () {
    $component = Livewire::test('gst-calculator')->set('amount', 10000)->set('rate', 18);

    $result = GstCalculator::addGst(10000, 18);
    $component->assertSee('₹'.IndianNumberFormatter::format($result['total_amount']));
});

it('shows the base amount excluding GST in remove mode', function () {
    $component = Livewire::test('gst-calculator')
        ->set('amount', 11800)
        ->set('rate', 18)
        ->call('selectMode', 'remove');

    $result = GstCalculator::removeGst(11800, 18);
    $component->assertSee('₹'.IndianNumberFormatter::format($result['base_amount']));
});

it('rejects a negative amount', function () {
    Livewire::test('gst-calculator')
        ->set('amount', -500)
        ->assertSet('amount', 0.0)
        ->assertHasErrors(['amount']);
});
