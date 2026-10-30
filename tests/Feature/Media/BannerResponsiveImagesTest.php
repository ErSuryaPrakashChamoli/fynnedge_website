<?php

use App\Models\Banner;
use App\Support\Media\MediaCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function storeBannerImage(string $name): string
{
    Storage::disk('public')->putFileAs('banners', UploadedFile::fake()->image($name, 900, 600), $name);

    return "banners/{$name}";
}

it('removes the old image\'s copies when a banner\'s image is replaced', function () {
    Storage::fake('public');
    $banner = Banner::factory()->create(['image_path' => storeBannerImage('old.jpg')]);

    $banner->update(['image_path' => storeBannerImage('new.jpg')]);

    expect(Storage::disk('public')->allFiles('responsive/banners'))->toEqualCanonicalizing([
        'responsive/banners/new-480.webp',
        'responsive/banners/new-768.webp',
        'responsive/banners/new-1080.webp',
    ]);
});

it('keeps the copies of an image another banner still shows when one banner is deleted', function () {
    Storage::fake('public');
    $path = storeBannerImage('shared.jpg');
    $banner = Banner::factory()->create(['image_path' => $path]);
    Banner::factory()->create(['image_path' => $path]);

    $banner->delete();

    Storage::disk('public')->assertExists('responsive/banners/shared-480.webp');
});

it('removes a deleted banner\'s copies when no other banner shows its image', function () {
    Storage::fake('public');
    $banner = Banner::factory()->create(['image_path' => storeBannerImage('promo.jpg')]);

    $banner->delete();

    expect(Storage::disk('public')->allFiles('responsive'))->toBeEmpty();
});

it('never offers the copies for deletion as unused uploads in Media Governance', function () {
    Storage::fake('public');
    Banner::factory()->create(['image_path' => storeBannerImage('promo.jpg')]);

    $paths = app(MediaCatalog::class)->all()->pluck('path');

    expect($paths)->toContain('banners/promo.jpg')
        ->not->toContain('responsive/banners/promo-480.webp');
});

it('backfills the copies of banner images uploaded before copies were made', function () {
    Storage::fake('public');
    Banner::factory()->create(['image_path' => storeBannerImage('promo.jpg')]);
    Storage::disk('public')->deleteDirectory('responsive');

    Artisan::call('media:responsive-images');

    expect(Artisan::output())->toContain('Checked 1 image(s), wrote 3 WebP copies.');
    Storage::disk('public')->assertExists([
        'responsive/banners/promo-480.webp',
        'responsive/banners/promo-768.webp',
        'responsive/banners/promo-1080.webp',
    ]);
});

it('leaves existing copies alone unless forced to rewrite them', function () {
    Storage::fake('public');
    Banner::factory()->create(['image_path' => storeBannerImage('promo.jpg')]);

    Artisan::call('media:responsive-images');
    $untouched = Artisan::output();
    Artisan::call('media:responsive-images', ['--force' => true]);

    expect($untouched)->toContain('wrote 0 WebP copies.')
        ->and(Artisan::output())->toContain('wrote 3 WebP copies.');
});
