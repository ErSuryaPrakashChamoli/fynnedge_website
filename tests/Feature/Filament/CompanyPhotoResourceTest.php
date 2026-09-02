<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\CompanyPhotos\Pages\CreateCompanyPhoto;
use App\Models\CompanyPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('creates one CompanyPhoto record per uploaded photo in a single batch', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateCompanyPhoto::class)
        ->fillForm([
            'photo_paths' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
            'caption' => 'Team offsite',
            'sort_order' => 5,
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CompanyPhoto::query()->count())->toBe(3);
    expect(CompanyPhoto::query()->pluck('caption')->unique()->all())->toBe(['Team offsite']);
    expect(CompanyPhoto::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([5, 6, 7]);
    expect(CompanyPhoto::query()->pluck('status')->unique()->all())->toEqual([PublishStatus::Published]);
});

it('rejects a batch of more than 8 photos', function () {
    $this->actingAs($this->admin);

    $photos = collect(range(1, 9))->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg"))->all();

    Livewire::test(CreateCompanyPhoto::class)
        ->fillForm(['photo_paths' => $photos])
        ->call('create')
        ->assertHasFormErrors(['photo_paths']);

    expect(CompanyPhoto::query()->count())->toBe(0);
});
