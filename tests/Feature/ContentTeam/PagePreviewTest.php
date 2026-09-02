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

it('hides the preview action for a page slug with no matching public route', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $page = Page::factory()->create(['slug' => 'some-unrouted-slug']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionHidden('preview');
});
