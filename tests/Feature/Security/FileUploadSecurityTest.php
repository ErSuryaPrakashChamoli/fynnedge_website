<?php

use App\Enums\LenderStatus;
use App\Enums\PublishStatus;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Lenders\Pages\CreateLender;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Phase 6.3 audit: every content image FileUpload only accepted the generic
 * image/* wildcard (via ->image()), which permits SVG — a format that can
 * carry an embedded <script> and (unlike raster formats) is not inert
 * everywhere it might be reached (a direct file URL, or a future embedding
 * change). Hardened to explicit raster-only MIME lists for pure content
 * photos, and added missing size caps on two fields that had none. Site/
 * lender logo fields intentionally keep SVG (legitimate brand-asset need,
 * high-trust upload path) — only their missing size cap was fixed.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('rejects an SVG upload for a banner image', function () {
    $svg = UploadedFile::fake()->createWithContent('malicious.svg', '<svg onload="alert(1)"></svg>');

    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => $svg,
            'heading' => 'Test',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['image_path']);
});

it('accepts a normal JPEG upload for a banner image', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('banner.jpg'),
            'heading' => 'Test',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('still allows an SVG for a lender logo, a legitimate brand-asset need', function () {
    Livewire::test(CreateLender::class)
        ->fillForm([
            'name' => 'SVG Logo Lender',
            'slug' => 'svg-logo-lender',
            'logo_path' => UploadedFile::fake()->createWithContent('logo.svg', '<svg></svg>'),
            'status' => LenderStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('enforces the newly added size cap on a testimonial avatar', function () {
    Livewire::test(CreateTestimonial::class)
        ->fillForm([
            'customer_name' => 'Big File Customer',
            'quote' => 'Test quote.',
            'avatar_path' => UploadedFile::fake()->image('avatar.jpg')->size(3000), // >2048KB cap
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['avatar_path']);
});
