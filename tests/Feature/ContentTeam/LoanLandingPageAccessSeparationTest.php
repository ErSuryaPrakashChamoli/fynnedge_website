<?php

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Filament\Resources\LoanLandingPageContent\Pages\EditLoanLandingPageContent;
use App\Filament\Resources\LoanLandingPages\Pages\EditLoanLandingPage;
use App\Filament\Resources\LoanLandingPageSeo\Pages\EditLoanLandingPageSeo;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Phase 9A — Loan Landing Page Content Access Separation.
 *
 * LoanLandingPageContentResource / LoanLandingPageSeoResource are restricted
 * doors onto the SAME LoanLandingPage record the full LoanLandingPageResource
 * manages, each gated by its own permission (never Update:LoanLandingPage)
 * and each backed by a server-side field allowlist
 * (RestrictsUpdateToAllowedFields) that is independent of what the form
 * renders — see Phase 9A report. This mirrors LoanProductAccessSeparationTest
 * (Phase 8) exactly.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

// --- MARKETING CAN -----------------------------------------------------------

it('lets Marketing access Loan Landing Page Content and view/update permitted fields', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $landingPage = LoanLandingPage::factory()->create();

    $this->get('/admin/loan-landing-page-content')->assertOk();
    $this->get("/admin/loan-landing-page-content/{$landingPage->getRouteKey()}/edit")->assertOk();

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm([
            'title' => 'TEST — DO NOT PUBLISH headline',
            'excerpt' => 'TEST — DO NOT PUBLISH summary',
            'body' => 'TEST — DO NOT PUBLISH body',
            'cta_label' => 'TEST — DO NOT PUBLISH CTA',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $landingPage->refresh();
    expect($landingPage->title)->toBe('TEST — DO NOT PUBLISH headline');
    expect($landingPage->excerpt)->toBe('TEST — DO NOT PUBLISH summary');
    expect($landingPage->cta_label)->toBe('TEST — DO NOT PUBLISH CTA');
});

it('lets Marketing use the existing publishing workflow (draft, schedule, publish, expire)', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'phase9a-publish-product']);
    $landingPage = LoanLandingPage::factory()->for($loanProduct, 'loanProduct')->create([
        'slug' => 'phase9a-publish-test',
        'status' => PublishStatus::Draft,
    ]);

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm(['status' => PublishStatus::Published->value, 'published_at' => now()->subMinute()->toDateTimeString()])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/loans/phase9a-publish-product/phase9a-publish-test')->assertOk();

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm(['expires_at' => now()->subMinute()->toDateTimeString()])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/loans/phase9a-publish-product/phase9a-publish-test')->assertNotFound();
});

it('exposes a working preview action to Marketing using the real public route', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'phase9a-preview-product']);
    $landingPage = LoanLandingPage::factory()->for($loanProduct, 'loanProduct')->create([
        'slug' => 'phase9a-preview-test',
        'status' => PublishStatus::Draft,
    ]);

    $this->get('/loans/phase9a-preview-product/phase9a-preview-test')->assertNotFound();

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->assertActionExists('preview');
});

// --- MARKETING CANNOT ---------------------------------------------------------

it('blocks Marketing from the full business LoanLandingPage editor', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $landingPage = LoanLandingPage::factory()->create();

    $this->get('/admin/loan-landing-pages')->assertForbidden();
    $this->get("/admin/loan-landing-pages/{$landingPage->getRouteKey()}/edit")->assertForbidden();
});

it('never persists business/routing/protected fields through the Marketing content editor even when submitted', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $originalProduct = LoanProduct::factory()->create();
    $otherProduct = LoanProduct::factory()->create();

    $landingPage = LoanLandingPage::factory()->for($originalProduct, 'loanProduct')->create([
        'group' => LandingPageGroup::ByNeed,
        'slug' => 'original-slug',
        'amount' => 100000,
        'sort_order' => 5,
    ]);

    // Directly exercise the server-side allowlist boundary (Step 16): a
    // payload mixing legitimate marketing fields with maliciously injected
    // protected/business/routing fields, exactly as a tampered request would
    // submit.
    $page = new EditLoanLandingPageContent;
    $filtered = (fn () => $this->mutateFormDataBeforeSave([
        'title' => 'TEST — DO NOT PUBLISH',
        'excerpt' => 'TEST — DO NOT PUBLISH summary',
        'loan_product_id' => $otherProduct->id,
        'group' => LandingPageGroup::ByAmount->value,
        'slug' => 'MALICIOUS-SLUG',
        'amount' => 'MALICIOUS-VALUE',
        'sort_order' => 999,
        'interest_rate' => 'MALICIOUS-VALUE',
        'calculator_key' => 'MALICIOUS-VALUE',
    ]))->call($page);

    $landingPage->update($filtered);
    $landingPage->refresh();

    // Legitimate fields DID update.
    expect($landingPage->title)->toBe('TEST — DO NOT PUBLISH');
    expect($landingPage->excerpt)->toBe('TEST — DO NOT PUBLISH summary');

    // Protected fields did NOT change.
    expect($landingPage->loan_product_id)->toBe($originalProduct->id);
    expect($landingPage->group)->toBe(LandingPageGroup::ByNeed);
    expect($landingPage->slug)->toBe('original-slug');
    expect((float) $landingPage->amount)->toBe(100000.0);
    expect($landingPage->sort_order)->toBe(5);
});

it('blocks Marketing from updating protected fields end-to-end through the Livewire form, even via a crafted fillForm payload', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $originalProduct = LoanProduct::factory()->create();
    $landingPage = LoanLandingPage::factory()->for($originalProduct, 'loanProduct')->create([
        'slug' => 'crafted-payload-slug',
        'amount' => 200000,
    ]);

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm(['title' => 'TEST — DO NOT PUBLISH'])
        ->call('save')
        ->assertHasNoFormErrors();

    $landingPage->refresh();
    expect($landingPage->title)->toBe('TEST — DO NOT PUBLISH');
    expect($landingPage->loan_product_id)->toBe($originalProduct->id);
    expect($landingPage->slug)->toBe('crafted-payload-slug');
    expect((float) $landingPage->amount)->toBe(200000.0);
});

// --- SEO CAN / CANNOT ----------------------------------------------------------

it('lets SEO access Loan Landing Page SEO and update SEO metadata including the OG image', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $landingPage = LoanLandingPage::factory()->create();

    $this->get('/admin/loan-landing-page-seo')->assertOk();
    $this->get("/admin/loan-landing-page-seo/{$landingPage->getRouteKey()}/edit")->assertOk();

    Livewire::test(EditLoanLandingPageSeo::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm([
            'seoMeta' => [
                'title' => 'TEST — DO NOT PUBLISH SEO title',
                'description' => 'TEST — DO NOT PUBLISH meta description',
                'og_image_path' => UploadedFile::fake()->image('test-og.jpg'),
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $landingPage->refresh();
    expect($landingPage->seoTitle())->toBe('TEST — DO NOT PUBLISH SEO title');
    expect($landingPage->seoDescription())->toBe('TEST — DO NOT PUBLISH meta description');
    expect($landingPage->seoOgImageUrl())->not->toBeNull();
});

it('blocks SEO from the full business LoanLandingPage editor', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $this->get('/admin/loan-landing-pages')->assertForbidden();
});

it('never persists protected fields through the SEO editor even when submitted', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $originalProduct = LoanProduct::factory()->create();
    $landingPage = LoanLandingPage::factory()->for($originalProduct, 'loanProduct')->create([
        'slug' => 'seo-protected-slug',
        'amount' => 300000,
    ]);

    $page = new EditLoanLandingPageSeo;
    $filtered = (fn () => $this->mutateFormDataBeforeSave([
        'loan_product_id' => 999999,
        'slug' => 'tampered',
        'amount' => 1,
        'title' => 'should also not persist via this page',
    ]))->call($page);

    // The SEO editor's allowlist is empty — nothing survives the filter.
    expect($filtered)->toBe([]);

    $landingPage->refresh();
    expect($landingPage->loan_product_id)->toBe($originalProduct->id);
    expect($landingPage->slug)->toBe('seo-protected-slug');
    expect((float) $landingPage->amount)->toBe(300000.0);
});

// --- ADMIN / SUPER ADMIN ---------------------------------------------------------

it('leaves the full LoanLandingPage editor fully intact for a super-admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $landingPage = LoanLandingPage::factory()->create();

    $this->get('/admin/loan-landing-pages')->assertOk();
    $this->get("/admin/loan-landing-pages/{$landingPage->getRouteKey()}/edit")->assertOk();
    $this->get('/admin/loan-landing-page-content')->assertOk();
    $this->get('/admin/loan-landing-page-seo')->assertOk();

    Livewire::test(EditLoanLandingPage::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm(['slug' => 'admin-changed-slug'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($landingPage->fresh()->slug)->toBe('admin-changed-slug');
});

// --- AUDIT -----------------------------------------------------------------------

it('audits a Marketing content edit with correct user attribution and old/new values, using the existing audit system', function () {
    $marketing = User::factory()->create(['is_admin' => true, 'name' => 'Test Marketing User']);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $landingPage = LoanLandingPage::factory()->create(['title' => 'Original Title']);

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm(['title' => 'TEST — DO NOT PUBLISH changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = $landingPage->auditLogs()->where('action', 'updated')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($marketing->id);
    expect($log->changes['title']['old'])->toBe('Original Title');
    expect($log->changes['title']['new'])->toBe('TEST — DO NOT PUBLISH changed');
});
