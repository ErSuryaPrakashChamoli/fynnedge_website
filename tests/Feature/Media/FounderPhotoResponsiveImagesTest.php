<?php

use App\Filament\Pages\Settings;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media\ResponsiveImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function storeFounderPhoto(string $name): string
{
    Storage::disk('public')->putFileAs('founder', UploadedFile::fake()->image($name, 900, 1125), $name);

    return "founder/{$name}";
}

/**
 * Removes the current photo before uploading the new one, as an admin does:
 * the field holds one file.
 */
function saveFounderPhotoInSettings(UploadedFile $photo): void
{
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test(Settings::class)
        ->fillForm(['founder_photo' => []])
        ->fillForm(['founder_photo' => $photo])
        ->call('save')
        ->assertHasNoFormErrors();
}

it('makes the About page copies of a founder photo saved in Settings', function () {
    Storage::fake('public');

    saveFounderPhotoInSettings(UploadedFile::fake()->image('founder.png', 1000, 1250));

    expect(ResponsiveImage::srcset(Setting::get('founder_photo')))
        ->toContain('-480.webp 480w')
        ->toContain('-1080.webp 1080w');
});

it('removes the old founder photo\'s copies when the photo is replaced', function () {
    Storage::fake('public');
    saveFounderPhotoInSettings(UploadedFile::fake()->image('old.png', 900, 1125));
    $oldPhoto = Setting::get('founder_photo');

    saveFounderPhotoInSettings(UploadedFile::fake()->image('new.png', 900, 1125));

    expect(ResponsiveImage::srcset($oldPhoto))->toBeNull()
        ->and(ResponsiveImage::srcset(Setting::get('founder_photo')))->not->toBeNull();
});

it('offers the founder photo\'s WebP copies on the About page, sized to its 280px box', function () {
    Storage::fake('public');
    $path = storeFounderPhoto('founder.jpg');
    ResponsiveImage::generate($path);
    Setting::set('founder_photo', $path);
    Page::factory()->published()->create(['slug' => 'about']);

    $this->get('/about')
        ->assertOk()
        ->assertSee('<source type="image/webp" srcset="/storage/responsive/founder/founder-480.webp 480w, /storage/responsive/founder/founder-768.webp 768w, /storage/responsive/founder/founder-1080.webp 1080w" sizes="280px">', false)
        ->assertSee('src="/storage/founder/founder.jpg"', false);
});

it('serves only the uploaded founder photo when its copies do not exist', function () {
    Storage::fake('public');
    Setting::set('founder_photo', storeFounderPhoto('founder.jpg'));
    Page::factory()->published()->create(['slug' => 'about']);

    $this->get('/about')
        ->assertOk()
        ->assertSee('src="/storage/founder/founder.jpg"', false)
        ->assertDontSee('<source type="image/webp"', false);
});

it('backfills the copies of a founder photo uploaded before copies were made', function () {
    Storage::fake('public');
    Setting::set('founder_photo', storeFounderPhoto('founder.jpg'));

    Artisan::call('media:responsive-images');

    expect(Artisan::output())->toContain('Checked 1 image(s), wrote 3 WebP copies.');
    Storage::disk('public')->assertExists('responsive/founder/founder-768.webp');
});
