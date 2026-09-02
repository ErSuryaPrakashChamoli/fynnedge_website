<?php

use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\SeoMeta;
use Illuminate\Support\Facades\Storage;

it('falls back to name/summary when a loan product has no SEO override', function () {
    $product = LoanProduct::factory()->create(['name' => 'Business Loan', 'summary' => 'Fast working capital.']);

    expect($product->seoTitle())->toBe('Business Loan');
    expect($product->seoDescription())->toBe('Fast working capital.');
});

it('prefers the SEO override over name/summary when one is set', function () {
    $product = LoanProduct::factory()->create(['name' => 'Business Loan', 'summary' => 'Fast working capital.']);
    $product->seoMeta()->save(new SeoMeta(['title' => 'Custom SEO Title', 'description' => 'Custom SEO description.']));

    expect($product->fresh()->seoTitle())->toBe('Custom SEO Title');
    expect($product->fresh()->seoDescription())->toBe('Custom SEO description.');
});

it('falls back to title/excerpt for a CMS page with no SEO override', function () {
    $page = Page::factory()->create(['title' => 'About FynnEdge', 'excerpt' => 'Who we are.']);

    expect($page->seoTitle())->toBe('About FynnEdge');
    expect($page->seoDescription())->toBe('Who we are.');
});

it('returns null description when there is no override and no summary/excerpt', function () {
    $page = Page::factory()->create(['title' => 'Untitled', 'excerpt' => null]);

    expect($page->seoDescription())->toBeNull();
});

it('returns null canonical/og image/robots when no SEO override is set', function () {
    $page = Page::factory()->create();

    expect($page->seoCanonicalUrl())->toBeNull();
    expect($page->seoOgImageUrl())->toBeNull();
    expect($page->seoRobots())->toBeNull();
});

it('resolves canonical url, og image url and robots from an SEO override', function () {
    Storage::fake('public');
    Storage::disk('public')->put('seo/custom.jpg', 'fake-image-content');

    $page = Page::factory()->create();
    $page->seoMeta()->save(new SeoMeta([
        'canonical_url' => 'https://fynnedge.com/canonical-target',
        'og_image_path' => 'seo/custom.jpg',
        'robots' => 'noindex, follow',
    ]));

    $page = $page->fresh();

    expect($page->seoCanonicalUrl())->toBe('https://fynnedge.com/canonical-target');
    expect($page->seoOgImageUrl())->toBe(Storage::disk('public')->url('seo/custom.jpg'));
    expect($page->seoRobots())->toBe('noindex, follow');
});
