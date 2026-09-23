<?php

use App\Filament\Pages\CreditScorePageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScorePageContent;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('opens showing the wording the public page currently uses', function () {
    $this->get('/admin/credit-score-page-settings')->assertOk();

    Livewire::test(CreditScorePageSettings::class)
        ->assertSet('data.heading', 'Check your free {bureau} score & report')
        ->assertSet('data.benefits_heading', 'Why check with FynnEdge?');
});

it('saves every section and shows it on that bureau page with the bureau name filled in', function () {
    Livewire::withQueryParams(['bureau' => 'experian'])->test(CreditScorePageSettings::class)
        ->fillForm([
            'meta_title' => '{bureau} score in two minutes',
            'meta_description' => 'Your {bureau} report, free.',
            'badge' => 'No-cost {bureau} check',
            'heading' => 'See your {bureau} score today',
            'description' => 'One OTP and you are done.',
            'benefits_heading' => 'Why us?',
            'benefits' => [['text' => 'Zero paperwork']],
            'stats' => [['value' => '100% online', 'label' => 'From any phone']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('credit-score.show', ['bureau' => 'experian']))
        ->assertOk()
        ->assertSee(['<title>Experian score in two minutes', 'Your Experian report, free.'], false)
        ->assertSee(['No-cost Experian check', 'See your Experian score today', 'One OTP and you are done.'])
        ->assertSee(['Why us?', 'Zero paperwork', '100% online', 'From any phone'])
        ->assertDontSee(['{bureau}', 'Why check with FynnEdge?', 'No cost, no hidden charges']);

});

it('keeps each bureau page independent of the others', function () {
    Livewire::withQueryParams(['bureau' => 'cibil'])->test(CreditScorePageSettings::class)
        ->fillForm(['heading' => 'Know your CIBIL number'])
        ->call('save');

    Livewire::withQueryParams(['bureau' => 'equifax'])->test(CreditScorePageSettings::class)
        ->assertSet('data.heading', 'Check your free {bureau} score & report')
        ->fillForm(['heading' => 'Equifax, explained'])
        ->call('save');

    $this->get(route('credit-score.show', ['bureau' => 'cibil']))
        ->assertSee('Know your CIBIL number')
        ->assertDontSee('Equifax, explained');

    $this->get(route('credit-score.show', ['bureau' => 'equifax']))
        ->assertSee('Equifax, explained')
        ->assertDontSee('Know your CIBIL number');

    $this->get(route('credit-score.show', ['bureau' => 'crif']))
        ->assertSee('Check your free CRIF score &amp; report', false);
});

it('shows the legacy shared copy on a bureau page until that page is saved on its own', function () {
    Setting::set(CreditScorePageContent::SETTING_KEY, ['heading' => 'Old shared {bureau} headline']);
    Setting::set(CreditScorePageContent::settingKey(BureauName::Cibil), ['heading' => 'CIBIL only']);

    expect(CreditScorePageContent::forBureau(BureauName::Experian)['heading'])->toBe('Old shared Experian headline')
        ->and(CreditScorePageContent::forBureau(BureauName::Cibil)['heading'])->toBe('CIBIL only');
});

it('returns not found for an unknown bureau', function () {
    $this->get('/admin/credit-score-page-settings?bureau=unknown')->assertNotFound();
});

it('falls back to the default wording for a blank field and hides a list that was emptied', function () {
    Livewire::test(CreditScorePageSettings::class)
        ->fillForm(['heading' => '', 'benefits' => [], 'stats' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('credit-score.show', ['bureau' => 'cibil']))
        ->assertOk()
        ->assertSee('Check your free CIBIL score &amp; report', false)
        ->assertDontSee('Why check with FynnEdge?')
        ->assertDontSee('No cost, no hidden charges');
});

it('drops a stored list item that is missing its required text', function () {
    Setting::set(CreditScorePageContent::settingKey(BureauName::Cibil), [
        'stats' => [['value' => '', 'label' => 'Orphaned caption'], ['value' => 'Kept', 'label' => '']],
    ]);

    expect(CreditScorePageContent::resolve(BureauName::Cibil)['stats'])->toBe([['value' => 'Kept', 'label' => '']]);
});

it('resets every field of one bureau page to the built-in wording, ignoring the legacy shared copy', function () {
    Setting::set(CreditScorePageContent::SETTING_KEY, ['badge' => 'An old shared badge']);
    Setting::set(CreditScorePageContent::settingKey(BureauName::Crif), ['badge' => 'An old CRIF badge']);
    Setting::set(CreditScorePageContent::settingKey(BureauName::Cibil), ['badge' => 'A CIBIL badge']);

    Livewire::withQueryParams(['bureau' => 'crif'])->test(CreditScorePageSettings::class)
        ->assertSet('data.badge', 'An old CRIF badge')
        ->call('resetToDefaults')
        ->assertSet('data.badge', 'Free {bureau} score check');

    expect(CreditScorePageContent::resolve(BureauName::Crif)['badge'])->toBe('Free {bureau} score check')
        ->and(CreditScorePageContent::resolve(BureauName::Cibil)['badge'])->toBe('A CIBIL badge');
});

it('is only available to admins holding the page permission, which the marketing role has', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor)->get('/admin/credit-score-page-settings')->assertForbidden();

    $this->seed(RoleSeeder::class);
    $editor->syncRoles(['Marketing']);

    $this->actingAs($editor->fresh())->get('/admin/credit-score-page-settings')->assertOk();
});
