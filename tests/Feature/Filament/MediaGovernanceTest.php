<?php

use App\Enums\PublishStatus;
use App\Filament\Pages\MediaGovernance;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Models\Banner;
use App\Models\CompanyPhoto;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media\MediaCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

it('discovers a lender logo — a Phase 6.4 registry gap fix', function () {
    Storage::disk('public')->put('lenders/acme.png', 'fake-image-bytes');
    Lender::factory()->create(['logo_path' => 'lenders/acme.png']);

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'lenders/acme.png');

    expect($entry)->not->toBeNull();
    expect($entry->status())->toBe('used');
    expect($entry->references->first()->modelLabel)->toBe('Lender Logo');
});

it('still counts a soft-deleted lender as using its logo', function () {
    Storage::disk('public')->put('lenders/trashed.png', 'fake-image-bytes');
    $lender = Lender::factory()->create(['logo_path' => 'lenders/trashed.png']);
    $lender->delete();

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'lenders/trashed.png');

    expect($entry->status())->toBe('used');
});

it('discovers a media reference from an existing content model', function () {
    Storage::disk('public')->put('banners/hero.jpg', 'fake-image-bytes');
    Banner::factory()->create(['image_path' => 'banners/hero.jpg']);

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'banners/hero.jpg');

    expect($entry)->not->toBeNull();
    expect($entry->status())->toBe('used');
    expect($entry->usageCount())->toBe(1);
    expect($entry->references->first()->modelLabel)->toBe('Banner');
});

it('counts multiple records referencing the same physical file', function () {
    Storage::disk('public')->put('loan-products/shared.jpg', 'fake-image-bytes');
    LoanProduct::factory()->create(['image_path' => 'loan-products/shared.jpg']);
    LoanProduct::factory()->create(['image_path' => 'loan-products/shared.jpg']);

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'loan-products/shared.jpg');

    expect($entry->usageCount())->toBe(2);
    expect($entry->status())->toBe('used');
});

it('identifies an unused file that exists on disk but is referenced by nothing', function () {
    Storage::disk('public')->put('company-photos/orphan.jpg', 'fake-image-bytes');

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'company-photos/orphan.jpg');

    expect($entry)->not->toBeNull();
    expect($entry->status())->toBe('unused');
    expect($entry->usageCount())->toBe(0);
});

it('identifies a missing file that is referenced but does not exist on disk', function () {
    Banner::factory()->create(['image_path' => 'banners/never-uploaded.jpg']);

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'banners/never-uploaded.jpg');

    expect($entry)->not->toBeNull();
    expect($entry->status())->toBe('missing');
    expect($entry->existsOnDisk)->toBeFalse();
});

it('discovers a site-wide Setting-based image alongside model-based media', function () {
    Storage::disk('public')->put('branding/logo.png', 'fake-image-bytes');
    Setting::set('site_logo', 'branding/logo.png');

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'branding/logo.png');

    expect($entry->status())->toBe('used');
    expect($entry->references->first()->modelLabel)->toBe('Site Settings');
});

it('still counts a soft-deleted record as using its file', function () {
    Storage::disk('public')->put('loan-products/trashed.jpg', 'fake-image-bytes');
    $product = LoanProduct::factory()->create(['image_path' => 'loan-products/trashed.jpg']);
    $product->delete();

    $entry = app(MediaCatalog::class)->all()->firstWhere('path', 'loan-products/trashed.jpg');

    expect($entry->status())->toBe('used');
    expect($entry->usageCount())->toBe(1);
});

it('does not break the existing Banner FileUpload — a normal upload still resolves as used media', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $file = UploadedFile::fake()->image('promo.jpg');

    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => $file,
            'heading' => 'Test banner',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->where('heading', 'Test banner')->sole();
    $entry = app(MediaCatalog::class)->all()->firstWhere('path', $banner->image_path);

    expect($entry->status())->toBe('used');
});

it('blocks a user without the View:MediaGovernance permission', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    $this->get('/admin/media-governance')->assertForbidden();
});

it('allows a super-admin to reach the media governance page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $this->get('/admin/media-governance')->assertOk();
});

it('allows a role explicitly granted View:MediaGovernance', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $user->givePermissionTo('View:MediaGovernance');
    $this->actingAs($user);

    $this->get('/admin/media-governance')->assertOk();
});

it('lets an admin delete an unused file', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Storage::disk('public')->put('company-photos/orphan.jpg', 'fake-image-bytes');

    Livewire::test(MediaGovernance::class)
        ->call('deleteUnused', 'public', 'company-photos/orphan.jpg');

    Storage::disk('public')->assertMissing('company-photos/orphan.jpg');
});

it('refuses to delete a file that is still referenced', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Storage::disk('public')->put('banners/in-use.jpg', 'fake-image-bytes');
    Banner::factory()->create(['image_path' => 'banners/in-use.jpg']);

    Livewire::test(MediaGovernance::class)
        ->call('deleteUnused', 'public', 'banners/in-use.jpg');

    Storage::disk('public')->assertExists('banners/in-use.jpg');
});

it('refuses to delete a file that became referenced between page load and the delete click', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Storage::disk('public')->put('company-photos/race.jpg', 'fake-image-bytes');

    $component = Livewire::test(MediaGovernance::class);

    // A reference appears after the page's initial catalog computation, simulating
    // another admin attaching the file to a record in between.
    CompanyPhoto::factory()->create(['photo_path' => 'company-photos/race.jpg']);

    $component->call('deleteUnused', 'public', 'company-photos/race.jpg');

    Storage::disk('public')->assertExists('company-photos/race.jpg');
});

it('filters the catalog by status', function () {
    Storage::disk('public')->put('banners/used.jpg', 'x');
    Storage::disk('public')->put('banners/unused.jpg', 'x');
    Banner::factory()->create(['image_path' => 'banners/used.jpg']);
    Banner::factory()->create(['image_path' => 'banners/missing-file.jpg']);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $component = Livewire::test(MediaGovernance::class)->set('status', 'unused');
    $paths = $component->instance()->filteredEntries()->pluck('path')->all();

    expect($paths)->toContain('banners/unused.jpg');
    expect($paths)->not->toContain('banners/used.jpg');
    expect($paths)->not->toContain('banners/missing-file.jpg');
});
