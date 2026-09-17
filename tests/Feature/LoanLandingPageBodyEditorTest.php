<?php

use App\Filament\Resources\LoanLandingPageContent\Pages\EditLoanLandingPageContent;
use App\Filament\Resources\LoanLandingPages\Pages\EditLoanLandingPage;
use App\Models\LoanLandingPage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

/*
 * Loan sub pages (landing pages) get the same body editor as loan products:
 * a visual rich editor with an "Edit as HTML" mode that keeps markup the
 * visual editor cannot represent, on both the full editor and the Marketing
 * content editor.
 */

it('saves the landing page body as raw HTML in HTML mode, removing scripts', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $landingPage = LoanLandingPage::factory()->create(['body' => '<p>Old body</p>']);

    Livewire::test(EditLoanLandingPage::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm([
            'body_html_mode' => true,
            'body_html' => '<div class="rate-card"><h2>Pre-Approved</h2><script>alert(1)</script></div>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($landingPage->refresh()->body)
        ->toContain('<div class="rate-card"><h2>Pre-Approved</h2>')
        ->not->toContain('script');
});

it('opens a landing page body the visual editor cannot represent in HTML mode so saving keeps its markup', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $body = '<div class="rate-card"><p>From 10.49%</p></div>';
    $landingPage = LoanLandingPage::factory()->create(['body' => $body]);

    Livewire::test(EditLoanLandingPage::class, ['record' => $landingPage->getRouteKey()])
        ->assertSchemaStateSet(['body_html_mode' => true, 'body_html' => $body])
        ->fillForm(['excerpt' => 'Updated summary'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($landingPage->refresh()->body)->toBe($body);
});

it('lets Marketing save raw HTML through the landing page content editor', function () {
    $this->seed(RoleSeeder::class);
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $landingPage = LoanLandingPage::factory()->create(['body' => '<p>Old body</p>']);

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm([
            'body_html_mode' => true,
            'body_html' => '<div class="rate-card"><p>Salaried applicants</p></div>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($landingPage->refresh()->body)->toBe('<div class="rate-card"><p>Salaried applicants</p></div>');
});
