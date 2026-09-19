<?php

use App\Filament\Pages\AboutPageSettings;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Pages\AboutPageContent;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Page::factory()->published()->create(['slug' => 'about', 'title' => 'About FynnEdge']);
});

it('opens showing the wording the public page currently uses', function () {
    $this->get('/admin/about-page-settings')->assertOk();

    Livewire::test(AboutPageSettings::class)
        ->assertSet('data.mission_title', 'Make borrowing simple, transparent and fair.')
        ->assertSet('data.work_heading', 'Work with us');
});

it('saves every section and shows it on the public about page', function () {
    Livewire::test(AboutPageSettings::class)
        ->fillForm([
            'founder_heading' => 'It started with one question:',
            'founder_heading_accent' => 'why is borrowing so hard?',
            'founder_points' => [['text' => 'We answer that every day.']],
            'founder_quote' => '"Clarity first."',
            'founder_role' => 'Founder & CEO',
            'mission_label' => 'Mission',
            'mission_title' => 'Fair credit for everyone.',
            'mission_body' => 'Clear reasons, no guesswork.',
            'vision_label' => 'Vision',
            'vision_title' => 'Credit you can compare.',
            'vision_body' => 'Choose with confidence.',
            'life_eyebrow' => 'Our team',
            'life_heading' => 'Small, focused, accountable.',
            'life_description' => 'Everyone ships.',
            'values' => [['title' => 'Candour', 'body' => 'We say what we mean.']],
            'work_heading' => 'Join us',
            'work_description' => 'We are hiring soon.',
            'work_button_label' => 'View careers',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/about')
        ->assertOk()
        ->assertSee(['It started with one question:', 'why is borrowing so hard?', 'We answer that every day.', '"Clarity first."', 'Founder & CEO'])
        ->assertSee(['Mission', 'Fair credit for everyone.', 'Clear reasons, no guesswork.', 'Vision', 'Credit you can compare.', 'Choose with confidence.'])
        ->assertSee(['Our team', 'Small, focused, accountable.', 'Everyone ships.', 'Candour', 'We say what we mean.'])
        ->assertSee(['Join us', 'We are hiring soon.', 'View careers'])
        ->assertSee(route('careers'), false)
        ->assertDontSee(['Make borrowing simple, transparent and fair.', 'Real ownership', 'See open roles']);
});

it('falls back to the default wording for a blank field and hides a list that was emptied', function () {
    Livewire::test(AboutPageSettings::class)
        ->fillForm(['mission_title' => '', 'founder_points' => [], 'values' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/about')
        ->assertOk()
        ->assertSee('Make borrowing simple, transparent and fair.')
        ->assertDontSee('finance should move people forward')
        ->assertDontSee('Real ownership');
});

it('drops a stored list item that is missing its required text', function () {
    Setting::set(AboutPageContent::SETTING_KEY, [
        'values' => [['title' => '', 'body' => 'Orphaned description'], ['title' => 'Kept', 'body' => '']],
    ]);

    expect(AboutPageContent::resolve()['values'])
        ->toHaveCount(1)
        ->sequence(fn ($value) => $value->title->toBe('Kept'));
});

it('resets every field to the built-in wording', function () {
    Setting::set(AboutPageContent::SETTING_KEY, ['work_heading' => 'An old custom heading']);

    Livewire::test(AboutPageSettings::class)
        ->assertSet('data.work_heading', 'An old custom heading')
        ->call('resetToDefaults')
        ->assertSet('data.work_heading', 'Work with us');

    expect(Setting::get(AboutPageContent::SETTING_KEY))->toBeNull();
});

it('is only available to admins holding the page permission, which the marketing role has', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor)->get('/admin/about-page-settings')->assertForbidden();

    $this->seed(RoleSeeder::class);
    $editor->syncRoles(['Marketing']);

    $this->actingAs($editor->fresh())->get('/admin/about-page-settings')->assertOk();
});
