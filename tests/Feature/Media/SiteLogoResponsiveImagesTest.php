<?php

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media\ResponsiveImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Removes the current logo before uploading the new one, as an admin does:
 * the field holds one file.
 */
function saveLogoInSettings(UploadedFile $logo): void
{
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test(Settings::class)
        ->fillForm(['site_logo' => []])
        ->fillForm(['site_logo' => $logo])
        ->call('save')
        ->assertHasNoFormErrors();
}

it('makes the small WebP copies of a logo saved in Settings, and drops the old logo\'s', function () {
    Storage::fake('public');
    saveLogoInSettings(UploadedFile::fake()->image('old.png', 3000, 3000));
    $oldLogo = Setting::get('site_logo');

    saveLogoInSettings(UploadedFile::fake()->image('new.png', 3000, 3000));

    expect(ResponsiveImage::srcset($oldLogo, widths: ResponsiveImage::LOGO_WIDTHS))->toBeNull();
    expect(ResponsiveImage::srcset(Setting::get('site_logo'), widths: ResponsiveImage::LOGO_WIDTHS))
        ->toStartWith('/storage/responsive/branding/')
        ->toContain('-64.webp 64w, ')
        ->toEndWith('-128.webp 128w');
});

it('serves the header and footer logo from its small copies', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs('branding', UploadedFile::fake()->image('logo.png', 3000, 3000), 'logo.png');
    ResponsiveImage::generate('branding/logo.png', widths: ResponsiveImage::LOGO_WIDTHS);
    Setting::set('site_logo', 'branding/logo.png');

    $this->get('/')
        ->assertSee('<source type="image/webp" srcset="/storage/responsive/branding/logo-64.webp 64w, /storage/responsive/branding/logo-128.webp 128w" sizes="36px">', false)
        ->assertSee('<source type="image/webp" srcset="/storage/responsive/branding/logo-64.webp 64w, /storage/responsive/branding/logo-128.webp 128w" sizes="32px">', false)
        ->assertSee('src="/storage/branding/logo.png"', false);
});

it('leaves an SVG logo vector, making no copies and reporting nothing', function () {
    Storage::fake('public');
    Exceptions::fake();
    Storage::disk('public')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
    Setting::set('site_logo', 'branding/logo.svg');

    Artisan::call('media:responsive-images');

    expect(Storage::disk('public')->allFiles('responsive'))->toBeEmpty();
    Exceptions::assertNothingReported();
});

it('serves an SVG logo as it is', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
    Setting::set('site_logo', 'branding/logo.svg');

    $this->get('/')
        ->assertSee('src="/storage/branding/logo.svg"', false)
        ->assertDontSee('responsive/branding', false);
});

it('backfills the copies of a logo uploaded before copies were made', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs('branding', UploadedFile::fake()->image('logo.png', 3000, 3000), 'logo.png');
    Setting::set('site_logo', 'branding/logo.png');

    Artisan::call('media:responsive-images');

    expect(Artisan::output())->toContain('Checked 1 image(s), wrote 2 WebP copies.');
    Storage::disk('public')->assertExists(['responsive/branding/logo-64.webp', 'responsive/branding/logo-128.webp']);
});
