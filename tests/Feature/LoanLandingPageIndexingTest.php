<?php

use App\Filament\Resources\LoanLandingPageSeo\Pages\EditLoanLandingPageSeo;
use App\Filament\Resources\LoanLandingPageSeo\Pages\ListLoanLandingPageSeo;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

/**
 * Per-page index/noindex for loan landing pages, set through the shared
 * SeoFormSection's "SEO indexing" field (seo_metas.robots).
 */
function robotsMetaTags(string $html): array
{
    preg_match_all('/<meta name="robots" content="([^"]*)">/', $html, $matches);

    return $matches[1];
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->loanProduct = LoanProduct::factory()->published()->create(['slug' => 'indexing-personal-loan']);
});

it('renders the robots value an admin saves for a loan landing page', function (string $robots) {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $landingPage = LoanLandingPage::factory()->published()->for($this->loanProduct, 'loanProduct')->create(['slug' => 'indexing-5-lakh']);

    Livewire::test(EditLoanLandingPageSeo::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm(['seoMeta' => ['robots' => $robots]])
        ->call('save')
        ->assertHasNoFormErrors();

    $html = $this->get('/loans/indexing-personal-loan/indexing-5-lakh')->assertOk()->getContent();

    expect(robotsMetaTags($html))->toBe([$robots]);
})->with(['index, follow', 'noindex, nofollow']);

it('keeps each loan landing page on its own robots setting', function () {
    $indexed = LoanLandingPage::factory()->published()->for($this->loanProduct, 'loanProduct')->create(['slug' => 'indexing-indexed']);
    $indexed->seoMeta()->create(['robots' => 'index, follow']);

    $hidden = LoanLandingPage::factory()->published()->for($this->loanProduct, 'loanProduct')->create(['slug' => 'indexing-hidden']);
    $hidden->seoMeta()->create(['robots' => 'noindex, nofollow', 'title' => 'Hidden landing page SEO title']);

    expect(robotsMetaTags($this->get('/loans/indexing-personal-loan/indexing-indexed')->getContent()))->toBe(['index, follow']);

    $this->get('/loans/indexing-personal-loan/indexing-hidden')
        ->assertSee('<title>Hidden landing page SEO title', false);
    expect(robotsMetaTags($this->get('/loans/indexing-personal-loan/indexing-hidden')->getContent()))->toBe(['noindex, nofollow']);
});

it('indexes a loan landing page that never had a robots setting', function () {
    LoanLandingPage::factory()->published()->for($this->loanProduct, 'loanProduct')->create(['slug' => 'indexing-untouched']);

    expect(robotsMetaTags($this->get('/loans/indexing-personal-loan/indexing-untouched')->getContent()))->toBe(['index, follow']);
});

it('shows each loan landing page\'s indexing setting in the SEO list', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $hidden = LoanLandingPage::factory()->for($this->loanProduct, 'loanProduct')->create();
    $hidden->seoMeta()->create(['robots' => 'noindex, nofollow']);

    Livewire::test(ListLoanLandingPageSeo::class)
        ->assertTableColumnStateSet('seoMeta.robots', 'noindex, nofollow', $hidden);
});
