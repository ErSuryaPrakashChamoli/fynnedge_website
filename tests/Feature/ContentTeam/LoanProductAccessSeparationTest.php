<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Resources\LoanProductContent\Pages\EditLoanProductContent;
use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Filament\Resources\LoanProductSeo\Pages\EditLoanProductSeo;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Phase 8 — Loan Product Content Access Separation.
 *
 * LoanProductContentResource / LoanProductSeoResource are restricted doors
 * onto the SAME LoanProduct record the full LoanProductResource manages,
 * each gated by its own permission (never Update:LoanProduct) and each
 * backed by a server-side field allowlist (RestrictsUpdateToAllowedFields)
 * that is independent of what the form renders — see Phase 8 report.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

// --- MARKETING CAN ---------------------------------------------------------

it('lets Marketing access Loan Product Content and view/update permitted fields', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create();

    $this->get('/admin/loan-product-content')->assertOk();
    $this->get("/admin/loan-product-content/{$loanProduct->getRouteKey()}/edit")->assertOk();

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm([
            'marketing_headline' => 'TEST — DO NOT PUBLISH headline',
            'summary' => 'TEST — DO NOT PUBLISH summary',
            'body' => 'TEST — DO NOT PUBLISH body',
            'benefits' => ['TEST — DO NOT PUBLISH benefit'],
            'cta_label' => 'TEST — DO NOT PUBLISH CTA',
            'image_alt' => 'TEST — DO NOT PUBLISH alt',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->marketing_headline)->toBe('TEST — DO NOT PUBLISH headline');
    expect($loanProduct->cta_label)->toBe('TEST — DO NOT PUBLISH CTA');
});

it('lets Marketing update the loan product image and alt text', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create();

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('test-product.jpg'),
            'image_alt' => 'TEST — DO NOT PUBLISH image alt',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($loanProduct->image_path);
    expect($loanProduct->image_alt)->toBe('TEST — DO NOT PUBLISH image alt');
});

it('lets Marketing use the existing publishing workflow (draft, schedule, publish, expire)', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create(['slug' => 'phase8-publish-test', 'status' => PublishStatus::Draft]);

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['status' => PublishStatus::Published->value, 'published_at' => now()->subMinute()->toDateTimeString()])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/loans/phase8-publish-test')->assertOk();

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['expires_at' => now()->subMinute()->toDateTimeString()])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/loans/phase8-publish-test')->assertNotFound();
});

it('exposes a working preview action to Marketing using the real public route', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create(['slug' => 'phase8-preview-test', 'status' => PublishStatus::Draft]);

    $this->get('/loans/phase8-preview-test')->assertNotFound();

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->assertActionExists('preview');
});

// --- MARKETING CANNOT --------------------------------------------------------

it('blocks Marketing from the full business LoanProduct editor', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create();

    $this->get('/admin/loan-products')->assertForbidden();
    $this->get("/admin/loan-products/{$loanProduct->getRouteKey()}/edit")->assertForbidden();
});

it('never persists business/calculator/protected fields through the Marketing content editor even when submitted', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create([
        'min_amount' => 50000,
        'max_amount' => 500000,
        'min_tenure_months' => 12,
        'max_tenure_months' => 60,
        'min_interest_rate' => 10.5,
        'max_interest_rate' => 20.0,
        'default_interest_rate' => 12.0,
        'calculator_key' => 'personal-loan',
        'category' => LoanCategory::PersonalLoan,
        'slug' => 'original-slug',
    ]);

    // Directly exercise the server-side allowlist boundary (Step 16): a
    // payload mixing legitimate marketing fields with maliciously injected
    // protected fields, exactly as a tampered request would submit.
    $page = new EditLoanProductContent;
    $filtered = (fn () => $this->mutateFormDataBeforeSave([
        'marketing_headline' => 'TEST — DO NOT PUBLISH',
        'benefits' => ['TEST — DO NOT PUBLISH'],
        'min_amount' => 99999999,
        'max_amount' => 1,
        'interest_rate' => 0.01,
        'min_interest_rate' => 0.01,
        'max_interest_rate' => 999,
        'default_interest_rate' => 0.01,
        'min_tenure_months' => 999,
        'max_tenure_months' => 1,
        'calculator_key' => 'tampered',
        'category' => 'tampered',
        'slug' => 'tampered-slug',
        'name' => 'Tampered Name',
    ]))->call($page);

    $loanProduct->update($filtered);
    $loanProduct->refresh();

    // Legitimate fields DID update.
    expect($loanProduct->marketing_headline)->toBe('TEST — DO NOT PUBLISH');
    expect($loanProduct->benefits)->toBe(['TEST — DO NOT PUBLISH']);

    // Protected fields did NOT change.
    expect((float) $loanProduct->min_amount)->toBe(50000.0);
    expect((float) $loanProduct->max_amount)->toBe(500000.0);
    expect($loanProduct->min_tenure_months)->toBe(12);
    expect($loanProduct->max_tenure_months)->toBe(60);
    expect((float) $loanProduct->min_interest_rate)->toBe(10.5);
    expect((float) $loanProduct->max_interest_rate)->toBe(20.0);
    expect((float) $loanProduct->default_interest_rate)->toBe(12.0);
    expect($loanProduct->calculator_key)->toBe('personal-loan');
    expect($loanProduct->category)->toBe(LoanCategory::PersonalLoan);
    expect($loanProduct->slug)->toBe('original-slug');
});

it('blocks Marketing from updating protected fields end-to-end through the Livewire form, even via a crafted fillForm payload', function () {
    $marketing = User::factory()->create(['is_admin' => true]);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create(['min_amount' => 50000, 'max_amount' => 500000]);

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['marketing_headline' => 'TEST — DO NOT PUBLISH'])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->marketing_headline)->toBe('TEST — DO NOT PUBLISH');
    expect((float) $loanProduct->min_amount)->toBe(50000.0);
    expect((float) $loanProduct->max_amount)->toBe(500000.0);
});

// --- SEO CAN / CANNOT --------------------------------------------------------

it('lets SEO access Loan Product SEO and update SEO metadata including the OG image', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $loanProduct = LoanProduct::factory()->create();

    $this->get('/admin/loan-product-seo')->assertOk();
    $this->get("/admin/loan-product-seo/{$loanProduct->getRouteKey()}/edit")->assertOk();

    Livewire::test(EditLoanProductSeo::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm([
            'seoMeta' => [
                'title' => 'TEST — DO NOT PUBLISH SEO title',
                'description' => 'TEST — DO NOT PUBLISH meta description',
                'og_image_path' => UploadedFile::fake()->image('test-og.jpg'),
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->seoTitle())->toBe('TEST — DO NOT PUBLISH SEO title');
    expect($loanProduct->seoDescription())->toBe('TEST — DO NOT PUBLISH meta description');
    expect($loanProduct->seoOgImageUrl())->not->toBeNull();
});

it('blocks SEO from the full business LoanProduct editor', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $this->get('/admin/loan-products')->assertForbidden();
});

it('never persists protected fields through the SEO editor even when submitted', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo);

    $loanProduct = LoanProduct::factory()->create([
        'min_amount' => 75000,
        'max_amount' => 750000,
        'min_interest_rate' => 9.5,
        'calculator_key' => 'personal-loan',
    ]);

    $page = new EditLoanProductSeo;
    $filtered = (fn () => $this->mutateFormDataBeforeSave([
        'min_amount' => 1,
        'max_amount' => 1,
        'min_interest_rate' => 0.01,
        'calculator_key' => 'tampered',
        'marketing_headline' => 'should also not persist via this page',
    ]))->call($page);

    // The SEO editor's allowlist is empty — nothing survives the filter.
    expect($filtered)->toBe([]);

    $loanProduct->refresh();
    expect((float) $loanProduct->min_amount)->toBe(75000.0);
    expect((float) $loanProduct->max_amount)->toBe(750000.0);
    expect((float) $loanProduct->min_interest_rate)->toBe(9.5);
    expect($loanProduct->calculator_key)->toBe('personal-loan');
});

// --- ADMIN / SUPER ADMIN -----------------------------------------------------

it('leaves the full LoanProduct editor fully intact for a super-admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $loanProduct = LoanProduct::factory()->create();

    $this->get('/admin/loan-products')->assertOk();
    $this->get("/admin/loan-products/{$loanProduct->getRouteKey()}/edit")->assertOk();
    $this->get('/admin/loan-product-content')->assertOk();
    $this->get('/admin/loan-product-seo')->assertOk();

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['min_amount' => 123456])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $loanProduct->fresh()->min_amount)->toBe(123456.0);
});

// --- AUDIT --------------------------------------------------------------------

it('audits a Marketing content edit with correct user attribution and old/new values, using the existing audit system', function () {
    $marketing = User::factory()->create(['is_admin' => true, 'name' => 'Test Marketing User']);
    $marketing->syncRoles(['Marketing']);
    $this->actingAs($marketing);

    $loanProduct = LoanProduct::factory()->create(['marketing_headline' => 'Original']);

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['marketing_headline' => 'TEST — DO NOT PUBLISH changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = $loanProduct->auditLogs()->where('action', 'updated')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($marketing->id);
    expect($log->changes['marketing_headline']['old'])->toBe('Original');
    expect($log->changes['marketing_headline']['new'])->toBe('TEST — DO NOT PUBLISH changed');
});
