<?php

use App\Enums\LoanCategory;
use App\Filament\Pages\CalculatorPagesSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Calculators\CalculatorPagesContent;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('opens showing the wording the public pages currently use', function () {
    $this->get('/admin/calculator-pages-settings')->assertOk();

    Livewire::test(CalculatorPagesSettings::class)
        ->assertSet('data.index.heading', 'All calculators')
        ->assertSet('data.emi.heading', '{loan} EMI Calculator')
        ->assertSet('data.gst.heading', 'GST Calculator');
});

it('saves the directory and investment calculator copy and shows it on those pages', function () {
    Livewire::test(CalculatorPagesSettings::class)
        ->fillForm([
            'index.meta_title' => 'Money calculators',
            'index.heading' => 'Every calculator we offer',
            'index.description' => 'Plan before you borrow.',
            'gst.heading' => 'Work out GST in seconds',
            'gst.description' => 'Add or strip GST instantly.',
            'gst.meta_description' => 'Free GST tool.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('calculators.index'))
        ->assertOk()
        ->assertSee(['<title>Money calculators'], false)
        ->assertSee(['Every calculator we offer', 'Plan before you borrow.'])
        ->assertDontSee('All calculators');

    $this->get(route('calculators.gst'))
        ->assertOk()
        ->assertSee(['Work out GST in seconds', 'Add or strip GST instantly.'])
        ->assertSee('<meta name="description" content="Free GST tool.">', false);
});

it('fills in the loan name on the loan calculator pages', function () {
    seedCalculatorProduct(LoanCategory::HomeLoan);

    Livewire::test(CalculatorPagesSettings::class)
        ->fillForm([
            'emi.heading' => 'Plan your {loan} repayments',
            'prepayment.about_heading' => 'How {loan} prepayment works',
            'prepayment.heading' => 'Pay off your {loan} sooner',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('calculators.emi', 'home-loan'))
        ->assertOk()
        ->assertSee('Plan your Home Loan repayments')
        ->assertDontSee('{loan}');

    $this->get(route('calculators.prepayment', 'home-loan'))
        ->assertOk()
        ->assertSee(['Pay off your Home Loan sooner', 'How Home Loan prepayment works'])
        ->assertDontSee('{loan}');
});

it('falls back to the default wording for a blank field', function () {
    Livewire::test(CalculatorPagesSettings::class)
        ->fillForm(['sip.heading' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('calculators.sip'))->assertOk()->assertSee('SIP Calculator');
});

it('resets every field to the built-in wording', function () {
    Setting::set(CalculatorPagesContent::SETTING_KEY, ['index' => ['heading' => 'An old custom heading']]);

    Livewire::test(CalculatorPagesSettings::class)
        ->assertSet('data.index.heading', 'An old custom heading')
        ->call('resetToDefaults')
        ->assertSet('data.index.heading', 'All calculators');

    expect(Setting::get(CalculatorPagesContent::SETTING_KEY))->toBeNull();
});

it('is only available to admins holding the page permission, which the marketing role has', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor)->get('/admin/calculator-pages-settings')->assertForbidden();

    $this->seed(RoleSeeder::class);
    $editor->syncRoles(['Marketing']);

    $this->actingAs($editor->fresh())->get('/admin/calculator-pages-settings')->assertOk();
});
