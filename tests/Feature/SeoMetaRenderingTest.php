<?php

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Models\SeoMeta;
use Illuminate\Support\Facades\Storage;

it('renders the admin-set canonical url, og:image and robots meta tags on a public page', function () {
    Storage::fake('public');
    Storage::disk('public')->put('seo/custom.jpg', 'fake-image-content');

    $page = Page::factory()->create([
        'slug' => 'disclaimer',
        'status' => PublishStatus::Published,
        'published_at' => now(),
    ]);
    $page->seoMeta()->save(new SeoMeta([
        'canonical_url' => 'https://fynnedge.com/canonical-target',
        'og_image_path' => 'seo/custom.jpg',
        'robots' => 'noindex, follow',
    ]));

    $ogImageUrl = Storage::disk('public')->url('seo/custom.jpg');

    $this->get('/disclaimer')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://fynnedge.com/canonical-target">', false)
        ->assertSee('<meta property="og:image" content="'.$ogImageUrl.'">', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('falls back to the current URL as canonical when no override is set', function () {
    Page::factory()->create([
        'slug' => 'terms',
        'status' => PublishStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/terms')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/terms').'">', false);
});
