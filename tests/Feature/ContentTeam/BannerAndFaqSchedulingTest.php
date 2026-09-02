<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Phase 7 content-team audit: Banner and Faq were the two content types the
 * audit checklist explicitly named for scheduling (seasonal campaign
 * banners, time-bound FAQs) but which only had a plain draft/publish
 * toggle. Extended with the existing Publishable trait — the same
 * published_at/expires_at mechanism every other schedulable content type
 * already uses, not a new one.
 */
it('does not show a banner scheduled to publish in the future', function () {
    Banner::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    $this->get('/')->assertOk();
    expect(Banner::query()->published()->count())->toBe(0);
});

it('stops showing a banner once it has expired', function () {
    Banner::factory()->create([
        'status' => PublishStatus::Published,
        'expires_at' => now()->subMinute(),
    ]);

    expect(Banner::query()->published()->count())->toBe(0);
});

it('does not show a FAQ scheduled to publish in the future', function () {
    Faq::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    expect(Faq::query()->published()->count())->toBe(0);
});

it('stops showing a FAQ once it has expired', function () {
    Faq::factory()->create([
        'status' => PublishStatus::Published,
        'expires_at' => now()->subMinute(),
    ]);

    expect(Faq::query()->published()->count())->toBe(0);
});

it('still shows an immediately-published banner with no scheduling set, unchanged from before', function () {
    Banner::factory()->published()->create();

    expect(Banner::query()->published()->count())->toBe(1);
});

it('accepts a site-relative banner CTA link, fixing an inconsistency with MarketingSection', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('banner.jpg'),
            'heading' => 'Test',
            'cta_url' => '/loans',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('still rejects a dangerous scheme on a banner CTA', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('banner.jpg'),
            'heading' => 'Test',
            'cta_url' => 'javascript:alert(1)',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['cta_url']);
});
