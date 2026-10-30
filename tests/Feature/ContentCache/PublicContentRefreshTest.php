<?php

use App\Enums\LandingPageGroup;
use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Resources\LoanLandingPages\Pages\EditLoanLandingPage;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
 * The editor's workflow end to end: a page is viewed (so its content is
 * cached), the content changes, and the very next view shows the change —
 * with no cache clear anywhere. app()->forgetScopedInstances() between views
 * starts a fresh request, as a real visitor's next page load would.
 */

function menuProductWithPage(array $pageAttributes = []): LoanLandingPage
{
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);

    return LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create([
        'group' => LandingPageGroup::ByAmount,
        'city' => null,
        'title' => '5 Lakh Personal Loan',
        'slug' => '5-lakh-personal-loan',
        ...$pageAttributes,
    ]);
}

function nextPageView(string $uri = '/'): TestResponse
{
    app()->forgetScopedInstances();

    return test()->get($uri);
}

it('shows a landing page title edited in the admin panel in the mega menu on the next page view', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('5 Lakh Personal Loan');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(EditLoanLandingPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['title' => 'Five Lakh Personal Loan'])
        ->call('save')
        ->assertHasNoFormErrors();

    nextPageView()->assertSee('Five Lakh Personal Loan')->assertDontSee('>5 Lakh Personal Loan<', false);
});

it('moves the mega menu link to a landing page\'s new URL when its slug changes', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('/loans/personal-loan/5-lakh-personal-loan');

    $page->update(['slug' => 'personal-loan-of-5-lakh']);

    nextPageView()
        ->assertSee('/loans/personal-loan/personal-loan-of-5-lakh')
        ->assertDontSee('/loans/personal-loan/5-lakh-personal-loan"', false);
});

it('adds a newly published landing page to the mega menu on the next page view', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('5 Lakh Personal Loan');

    LoanLandingPage::factory()->published()->for($page->loanProduct, 'loanProduct')->create([
        'group' => LandingPageGroup::ByAmount,
        'title' => '10 Lakh Personal Loan',
        'slug' => '10-lakh-personal-loan',
    ]);

    nextPageView()->assertSee('10 Lakh Personal Loan');
});

it('drops an unpublished landing page from the mega menu on the next page view', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('5 Lakh Personal Loan');

    $page->update(['status' => PublishStatus::Draft]);

    nextPageView()->assertDontSee('5 Lakh Personal Loan');
});

it('drops a deleted landing page from the mega menu on the next page view', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('5 Lakh Personal Loan');

    $page->delete();

    nextPageView()->assertDontSee('5 Lakh Personal Loan');
});

it('drops every landing page of a loan product taken off the site from the mega menu', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('5 Lakh Personal Loan');

    $page->loanProduct->update(['status' => PublishStatus::Draft]);

    nextPageView()->assertDontSee('5 Lakh Personal Loan');
});

it('adds a landing page scheduled for later to the mega menu when its time comes, without any edit', function () {
    $this->travelTo('2026-10-30 09:00:00');
    menuProductWithPage(['published_at' => '2026-10-30 10:00:00']);
    $this->get('/')->assertDontSee('5 Lakh Personal Loan');

    $this->travelTo('2026-10-30 10:00:01');

    nextPageView()->assertSee('5 Lakh Personal Loan');
});

it('keeps the mega menu unchanged while an edit is still uncommitted', function () {
    $page = menuProductWithPage();
    $this->get('/')->assertSee('5 Lakh Personal Loan');

    try {
        DB::transaction(function () use ($page): void {
            $page->update(['title' => 'Never saved']);

            throw new RuntimeException('Save failed');
        });
    } catch (RuntimeException) {
    }

    nextPageView()->assertSee('5 Lakh Personal Loan')->assertDontSee('Never saved');
});

it('lists a newly published loan product in the footer on the next page view', function () {
    LoanProduct::factory()->published()->create(['name' => 'Personal Loan', 'slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    $this->get('/')->assertSee('/loans/personal-loan');

    LoanProduct::factory()->published()->create(['name' => 'Home Loan', 'slug' => 'home-loan', 'category' => LoanCategory::HomeLoan]);

    nextPageView()->assertSee('/loans/home-loan');
});

it('shows a renamed site on every page from the next page view', function () {
    Setting::set('site_name', 'FynnEdge');
    $this->get('/')->assertSee('FynnEdge');

    Setting::set('site_name', 'FynnEdge Advisory');

    nextPageView()->assertSee('FynnEdge Advisory');
});

it('serves a redirect added in the admin panel from the next request', function () {
    $this->get('/old-offer')->assertNotFound();

    Redirect::factory()->create(['source_path' => '/old-offer', 'destination' => '/loans', 'status_code' => 301, 'is_active' => true]);

    nextPageView('/old-offer')->assertRedirect('/loans');
});

it('stops serving a redirect switched off in the admin panel from the next request', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/old-offer', 'destination' => '/loans', 'status_code' => 301, 'is_active' => true]);
    $this->get('/old-offer')->assertRedirect('/loans');

    $redirect->update(['is_active' => false]);

    nextPageView('/old-offer')->assertNotFound();
});

it('never shows a guest the header a signed-in customer saw', function () {
    menuProductWithPage();
    $this->actingAs(Customer::factory()->portalAccount()->create(), 'customer')
        ->get('/')
        ->assertSee('My Profile');

    Auth::guard('customer')->logout();

    nextPageView()->assertSee('Sign in')->assertDontSee('My Profile');
});

it('keeps serving current content from the database while the cache store is down', function () {
    unreachableCacheStore();

    Setting::set('site_name', 'FynnEdge Advisory');
    menuProductWithPage();

    $this->get('/')->assertSee('FynnEdge Advisory')->assertSee('5 Lakh Personal Loan');
});
