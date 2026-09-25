<?php

use App\Filament\Resources\CalculatorPages\Pages\CreateCalculatorPage;
use App\Filament\Resources\CalculatorPages\Pages\EditCalculatorPage;
use App\Filament\Resources\CalculatorPages\Pages\ListCalculatorPages;
use App\Models\CalculatorPage;
use App\Models\User;
use App\Support\Calculators\CalculatorCatalog;
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

it('creates About content for a loan calculator page', function () {
    Livewire::test(CreateCalculatorPage::class)
        ->fillForm([
            'calculator_key' => 'emi/home-loan',
            'body' => '<p>TEST ABOUT HOME LOAN EMI CALCULATOR CONTENT</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CalculatorPage::query()->where('calculator_key', 'emi/home-loan')->value('body'))
        ->toContain('TEST ABOUT HOME LOAN EMI CALCULATOR CONTENT');
});

it('rejects a calculator key that is not a real calculator page', function () {
    Livewire::test(CreateCalculatorPage::class)
        ->fillForm(['calculator_key' => 'emi/credit-card', 'body' => '<p>Nowhere to show this.</p>'])
        ->call('create')
        ->assertHasFormErrors(['calculator_key']);
});

it('saves a calculator page its own headline and introduction', function () {
    Livewire::test(CreateCalculatorPage::class)
        ->fillForm([
            'calculator_key' => 'emi/flexi-hybrid-term-loan',
            'heading' => 'Flexi Hybrid EMI headline',
            'description' => 'Flexi Hybrid EMI introduction.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CalculatorPage::query()->where('calculator_key', 'emi/flexi-hybrid-term-loan')->first())
        ->heading->toBe('Flexi Hybrid EMI headline')
        ->description->toBe('Flexi Hybrid EMI introduction.');
});

it('keeps an entry on its own calculator page when it is edited', function () {
    $page = CalculatorPage::factory()->create(['calculator_key' => 'emi/home-loan', 'heading' => 'Home loan EMI headline']);

    Livewire::test(EditCalculatorPage::class, ['record' => $page->getRouteKey()])
        ->assertFormFieldDisabled('calculator_key')
        ->fillForm(['calculator_key' => 'eligibility/home-loan', 'heading' => 'Updated headline'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh())
        ->calculator_key->toBe('emi/home-loan')
        ->heading->toBe('Updated headline');
});

it('lists every calculator page ready to edit, keeping existing content', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'gst', 'heading' => 'Existing GST headline']);

    Livewire::test(ListCalculatorPages::class)
        ->assertSuccessful();

    expect(CalculatorPage::query()->pluck('calculator_key')->sort()->values()->all())
        ->toBe(collect(CalculatorCatalog::pages())->keys()->sort()->values()->all())
        ->and(CalculatorPage::query()->where('calculator_key', 'gst')->value('heading'))->toBe('Existing GST headline');
});
