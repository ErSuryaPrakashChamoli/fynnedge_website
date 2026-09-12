<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('stores the uploaded image on the public disk, where the model and table read it back', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('promo.jpg'),
            'heading' => 'Marketing',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->where('heading', 'Marketing')->sole();

    /*
     * The disk the form writes to must be the disk everything else reads:
     * Banner::imageUrl() and BannersTable's ImageColumn both go through
     * Storage::disk('public'), and the ImageColumn renders nothing at all
     * when exists() is false — an empty Image cell in the admin means the
     * file is not on this disk, not that the URL is wrong.
     */
    expect($banner->image_path)->toStartWith('banners/');
    Storage::disk('public')->assertExists($banner->image_path);
    expect($banner->imageUrl())->toContain('/storage/'.$banner->image_path);
});

it('fails loudly instead of saving an imageless banner when the public disk cannot be written', function () {
    /*
     * The server failure this guards: storage/app/public exists but php-fpm
     * cannot write into it. Flysystem raises UnableToWriteFile, and while the
     * public disk had throw => false Laravel swallowed it and returned false —
     * which Filament reads as "no file uploaded", dropping image_path and
     * saving a banner that looks fine in the list but has no image and showed
     * the admin no error at all.
     */
    $throwingAdapter = new class(Storage::disk('public')->path('')) extends LocalFilesystemAdapter
    {
        public function writeStream(string $path, $contents, Config $config): void
        {
            throw UnableToWriteFile::atLocation($path, 'permission denied');
        }
    };

    Storage::set('public', new FilesystemAdapter(
        new Filesystem($throwingAdapter),
        $throwingAdapter,
        config('filesystems.disks.public'),
    ));

    expect(fn () => Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('promo.jpg'),
            'heading' => 'Marketing',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create'))
        ->toThrow(UnableToWriteFile::class);

    expect(Banner::query()->count())->toBe(0);
});
