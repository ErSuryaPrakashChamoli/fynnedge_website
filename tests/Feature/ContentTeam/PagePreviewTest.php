<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Phase 7 audit finding: Page (static pages — about, privacy-policy, terms,
 * disclaimer, grievance, credit-report-terms) was the one Publishable,
 * previewable-in-principle content type missing the Preview action that
 * LoanProduct/Article/LoanLandingPage already had — a real structural
 * inconsistency (see Step 11). Fixed via the same signed-URL mechanism,
 * adapted for Page's route-name-equals-slug convention.
 */
it('lets an admin preview a draft static page via a signed URL, but not via a plain URL', function () {
    $page = Page::factory()->create(['slug' => 'privacy-policy', 'status' => PublishStatus::Draft]);

    $this->get('/privacy-policy')->assertNotFound();

    $signedUrl = URL::temporarySignedRoute('privacy-policy', now()->addMinutes(30));

    $this->get($signedUrl)->assertOk()->assertSee($page->title);
});

it('lets an admin preview a draft about page via a signed URL', function () {
    $page = Page::factory()->create(['slug' => 'about', 'title' => 'About Us Draft', 'status' => PublishStatus::Draft]);

    $this->get('/about')->assertNotFound();

    $signedUrl = URL::temporarySignedRoute('about', now()->addMinutes(30));

    $this->get($signedUrl)->assertOk()->assertSee('About Us Draft');
});

it('exposes a working preview action on the page edit page for a routable slug', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $page = Page::factory()->create(['slug' => 'terms']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionExists('preview');
});

it('hides the preview action for a page slug no public route serves', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    // /contact belongs to ContactController, so a page with that slug can never be shown.
    $page = Page::factory()->create(['slug' => 'contact']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionHidden('preview');
});

it('previews a new general page at its own address', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    // Since 2026-11-24 any other slug is served at /{slug} (Sitemap::GENERAL_PAGE_ROUTE).
    $page = Page::factory()->create(['slug' => 'some-new-page', 'title' => 'Some new page']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionVisible('preview');

    $this->get('/some-new-page')->assertNotFound();
    $this->get(URL::temporarySignedRoute('pages.show', now()->addMinutes(30), ['slug' => 'some-new-page']))->assertOk()->assertSee('Some new page');
});

it('lets an admin preview a draft FAQs page via a signed URL', function () {
    Page::query()->where('slug', 'faqs')->sole()->update(['title' => 'FAQs Draft', 'status' => PublishStatus::Draft]);

    $this->get('/faqs')->assertNotFound();

    $this->get(URL::temporarySignedRoute('faqs.index', now()->addMinutes(30)))->assertOk()->assertSee('FAQs Draft');
});

it('shows the preview action for the FAQs page, whose route is not named after its slug', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(EditPage::class, ['record' => Page::query()->where('slug', 'faqs')->sole()->getRouteKey()])
        ->assertActionVisible('preview');
});
