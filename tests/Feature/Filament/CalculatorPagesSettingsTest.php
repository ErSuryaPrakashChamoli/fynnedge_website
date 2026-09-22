<?php

use App\Enums\LoanCategory;
use App\Filament\Pages\CalculatorPagesSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Calculators\CalculatorIndexing;
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

it('lets every calculator page be indexed until an admin hides it', function () {
    Livewire::test(CalculatorPagesSettings::class)
        ->assertSet('data.indexing.enabled', true)
        ->assertSet('data.indexing.noindex', []);

    $this->get(route('calculators.gst'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', false);
});

it('hides a single calculator page from search engines and the sitemap', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);

    Livewire::test(CalculatorPagesSettings::class)
        ->fillForm(['indexing.noindex' => ['gst', 'emi/home-loan']])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('calculators.gst'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    $this->get(route('calculators.emi', 'personal-loan'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee(['<loc>'.route('calculators.gst').'</loc>', '<loc>'.route('calculators.emi', 'home-loan').'</loc>'], false)
        ->assertSee(['<loc>'.route('calculators.index').'</loc>', '<loc>'.route('calculators.emi', 'personal-loan').'</loc>'], false);
});

it('hides the whole calculator section with the main switch', function () {
    Livewire::test(CalculatorPagesSettings::class)
        ->fillForm(['indexing.enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('calculators.index'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    $this->get(route('calculators.sip'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('calculators.index'));
});

it('keeps the indexing choices when the wording is reset', function () {
    Setting::set(CalculatorIndexing::SETTING_KEY, ['enabled' => true, 'noindex' => ['sip']]);

    Livewire::test(CalculatorPagesSettings::class)
        ->call('resetToDefaults')
        ->assertSet('data.indexing.noindex', ['sip']);

    expect(CalculatorIndexing::isIndexable('sip'))->toBeFalse();
});
