<?php

use App\Filament\Resources\CalculatorPages\Pages\CreateCalculatorPage;
use App\Filament\Resources\CalculatorPages\Pages\ListCalculatorPages;
use App\Models\CalculatorPage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists calculator pages', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'gst']);

    Livewire::test(ListCalculatorPages::class)
        ->assertSuccessful();
});

it('creates a calculator page', function () {
    Livewire::test(CreateCalculatorPage::class)
        ->fillForm([
            'calculator_key' => 'sip',
            'title' => 'About SIPs',
            'body' => '<p>New SIP copy.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CalculatorPage::query()->where('calculator_key', 'sip')->exists())->toBeTrue();
});

it('requires a unique calculator key', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'gst']);

    Livewire::test(CreateCalculatorPage::class)
        ->fillForm(['calculator_key' => 'gst', 'body' => '<p>Duplicate.</p>'])
        ->call('create')
        ->assertHasFormErrors(['calculator_key']);
});
