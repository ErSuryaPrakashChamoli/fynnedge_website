<?php

use App\Enums\LenderStatus;
use App\Filament\Pages\QuickEnquiryPageSettings;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Support\Enquiries\QuickEnquiryPageContent;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('opens showing the wording the public page currently uses', function () {
    $this->get('/admin/quick-enquiry-page-settings')->assertOk();

    Livewire::test(QuickEnquiryPageSettings::class)
        ->assertSet('data.heading', 'Tell us what you need.')
        ->assertSet('data.home_button_label', 'Quick Enquiry');
});

it('saves the page content and shows it on the public page and homepage button', function () {
    LoanProduct::factory()->published()->create();

    Livewire::test(QuickEnquiryPageSettings::class)
        ->fillForm([
            'home_button_label' => 'Call Me Back',
            'badge' => 'Fast Enquiry',
            'heading' => 'Need funds fast?',
            'heading_accent' => 'We are on it.',
            'assurances' => [['text' => 'Free expert advice']],
            'steps' => [['title' => 'Share your details', 'body' => 'Two minutes, tops.']],
            'form_eyebrow' => 'Callback Request',
            'form_headline' => 'Tell us about your loan',
            'form_cta_label' => 'Request Callback',
            'explore_links' => [['label' => 'Browse Home Loans', 'url' => '/loans', 'body' => '', 'link_text' => 'See loans']],
            'meta_title' => 'Fast Loan Enquiry',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/quick-enquiry')
        ->assertOk()
        ->assertSee(['Fast Loan Enquiry', 'Fast Enquiry', 'Need funds fast?', 'We are on it.'])
        ->assertSee(['Free expert advice', 'Share your details', 'Two minutes, tops.'])
        ->assertSee(['Callback Request', 'Tell us about your loan', 'Request Callback'])
        ->assertSee(['Browse Home Loans', 'See loans'])
        ->assertDontSee(['Takes under a minute', 'We shortlist lenders', 'Check Your Eligibility']);

    $this->get('/')->assertOk()->assertSee('Call Me Back');
});

it('falls back to the default wording for a blank field and hides a list that was emptied', function () {
    Livewire::test(QuickEnquiryPageSettings::class)
        ->fillForm(['heading' => '', 'assurances' => [], 'explore_links' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/quick-enquiry')
        ->assertOk()
        ->assertSee('Tell us what you need.')
        ->assertSee('We shortlist lenders')
        ->assertDontSee('Takes under a minute')
        ->assertDontSee('Prefer to look around first?');
});

it('rejects a card link that is not a web address or site path', function () {
    Livewire::test(QuickEnquiryPageSettings::class)
        ->fillForm(['explore_links' => [['label' => 'Bad link', 'url' => 'javascript:alert(1)']]])
        ->call('save')
        ->assertHasFormErrors();

    expect(Setting::get(QuickEnquiryPageContent::SETTING_KEY))->toBeNull();
});

it('never renders an unsafe link even if one reaches the stored setting', function () {
    Setting::set(QuickEnquiryPageContent::SETTING_KEY, [
        'explore_links' => [
            ['label' => 'Bad link', 'url' => 'javascript:alert(1)'],
            ['label' => 'Good link', 'url' => '/loans'],
        ],
    ]);

    expect(QuickEnquiryPageContent::resolve()['explore_links'])
        ->toHaveCount(1)
        ->sequence(fn ($link) => $link->label->toBe('Good link'));
});

it('hides the partner lenders when switched off', function () {
    Lender::factory()->create(['status' => LenderStatus::Active]);

    $this->get('/quick-enquiry')->assertSee('Our partner banks & NBFCs');

    Setting::set(QuickEnquiryPageContent::SETTING_KEY, ['show_lenders' => false]);

    $this->get('/quick-enquiry')->assertDontSee('Our partner banks & NBFCs');
});

it('resets every field to the built-in wording', function () {
    Setting::set(QuickEnquiryPageContent::SETTING_KEY, ['heading' => 'An old custom heading']);

    Livewire::test(QuickEnquiryPageSettings::class)
        ->assertSet('data.heading', 'An old custom heading')
        ->call('resetToDefaults')
        ->assertSet('data.heading', 'Tell us what you need.');

    expect(Setting::get(QuickEnquiryPageContent::SETTING_KEY))->toBeNull();
});

it('is only available to admins holding the page permission, which the marketing role has', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor)->get('/admin/quick-enquiry-page-settings')->assertForbidden();

    $this->seed(RoleSeeder::class);
    $editor->syncRoles(['Marketing']);

    $this->actingAs($editor->fresh())->get('/admin/quick-enquiry-page-settings')->assertOk();
});
