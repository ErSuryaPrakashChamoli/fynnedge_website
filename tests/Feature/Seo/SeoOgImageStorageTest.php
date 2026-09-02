<?php

use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\Media\MediaCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Regression test for the Phase 6.1 fix: SeoFormSection's og_image_path
 * FileUpload previously omitted ->disk('public'), so with
 * FILESYSTEM_DISK=local every SEO social-share image silently landed on the
 * private disk and was unreachable — Seoable::seoOgImageUrl() always builds
 * its URL via Storage::disk('public'). This exercises the complete lifecycle
 * end to end rather than just checking the form field's disk() config.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('stores an uploaded SEO OG image on the public disk, not the private one', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'seo-og-test']);
    $file = UploadedFile::fake()->image('share.jpg', 1200, 630);

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['seoMeta' => ['og_image_path' => $file]])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    $path = $loanProduct->seoMeta->og_image_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
    Storage::disk('local')->assertMissing($path);
});

it('resolves the OG image to a publicly reachable URL via the Seoable accessor', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'seo-og-url-test']);
    $loanProduct->seoMeta()->create(['og_image_path' => 'seo/share.jpg']);
    Storage::disk('public')->put('seo/share.jpg', 'fake-image-bytes');

    expect($loanProduct->seoOgImageUrl())->toBe(Storage::disk('public')->url('seo/share.jpg'));
});

it('outputs the correct OG image meta tag on the public loan product page', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'seo-og-render-test']);
    $loanProduct->seoMeta()->create(['og_image_path' => 'seo/share.jpg']);
    Storage::disk('public')->put('seo/share.jpg', 'fake-image-bytes');

    $this->get('/loans/seo-og-render-test')
        ->assertOk()
        ->assertSee(Storage::disk('public')->url('seo/share.jpg'), false);
});

it('falls back to the site default OG image when a page has none of its own', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'seo-og-fallback-test']);

    $response = $this->get('/loans/seo-og-fallback-test');

    $response->assertOk();
    expect($loanProduct->seoOgImageUrl())->toBeNull();
});

it('is identified as Used, not Missing, by Media Governance once uploaded', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'seo-og-governance-test']);
    $loanProduct->seoMeta()->create(['og_image_path' => 'seo/governed.jpg']);
    Storage::disk('public')->put('seo/governed.jpg', 'fake-image-bytes');

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'seo/governed.jpg');

    expect($entry)->not->toBeNull();
    expect($entry->status())->toBe('used');
    expect($entry->references->first()->modelLabel)->toBe('SEO Social Image');
});
