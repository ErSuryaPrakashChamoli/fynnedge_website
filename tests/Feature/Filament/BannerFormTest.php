<?php

use App\Enums\BannerHorizontalAlignment;
use App\Enums\BannerVerticalAlignment;
use App\Enums\PublishStatus;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('saves a banner\'s own button colours and content position', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('promo.jpg'),
            'cta_label' => 'Apply Now',
            'cta_url' => '/loans',
            'cta_bg_color' => '#c62828',
            'cta_hover_color' => '#8e0000',
            'cta_text_color' => '#111111',
            'content_vertical_align' => BannerVerticalAlignment::Center->value,
            'content_horizontal_align' => BannerHorizontalAlignment::Right->value,
            'content_padding_left' => 0,
            'content_padding_right' => 12,
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->sole();

    expect($banner->cta_bg_color)->toBe('#c62828')
        ->and($banner->cta_hover_color)->toBe('#8e0000')
        ->and($banner->cta_text_color)->toBe('#111111')
        ->and($banner->content_vertical_align)->toBe(BannerVerticalAlignment::Center)
        ->and($banner->content_horizontal_align)->toBe(BannerHorizontalAlignment::Right)
        ->and($banner->content_padding_left)->toBe(0)
        ->and($banner->content_padding_right)->toBe(12);
});

it('rejects a colour that is not a hex value and spacing beyond the maximum', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('promo.jpg'),
            'cta_bg_color' => 'red;} body{display:none}',
            'content_padding_left' => Banner::MAX_CONTENT_PADDING + 1,
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['cta_bg_color', 'content_padding_left']);

    expect(Banner::query()->count())->toBe(0);
});
