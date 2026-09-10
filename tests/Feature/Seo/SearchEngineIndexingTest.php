<?php

use App\Models\Setting;
use App\Support\Seo\SearchEngineIndexing;

it('is indexable by default, so a site with no saved setting never falls out of search', function () {
    expect(SearchEngineIndexing::enabled())->toBeTrue();

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertHeaderMissing('X-Robots-Tag');
});

it('keeps a page-level robots value when sitewide indexing is on', function () {
    expect(SearchEngineIndexing::metaRobots('noindex, follow'))->toBe('noindex, follow');
});

it('sends noindex on every public page when indexing is switched off', function () {
    Setting::set('seo_indexing_enabled', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('content="index, follow"', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('overrides a page that asked to be indexed when indexing is switched off', function () {
    Setting::set('seo_indexing_enabled', false);

    expect(SearchEngineIndexing::metaRobots('index, follow'))->toBe('noindex, nofollow');
});

it('falls back to the configured default when the setting has never been saved', function () {
    config()->set('seo.indexing_enabled', false);

    expect(SearchEngineIndexing::enabled())->toBeFalse();
});

it('lets a saved setting win over the environment default', function () {
    config()->set('seo.indexing_enabled', false);
    Setting::set('seo_indexing_enabled', true);

    expect(SearchEngineIndexing::enabled())->toBeTrue();
});

it('serves a crawlable robots.txt advertising the sitemap while indexing is on', function () {
    $response = $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain('Allow: /')
        ->toContain('Disallow: /admin')
        ->toContain('Sitemap: '.route('sitemap'))
        ->not->toContain("User-agent: *\nDisallow: /");
});

it('disallows everything in robots.txt and drops the sitemap line when indexing is off', function () {
    Setting::set('seo_indexing_enabled', false);

    $response = $this->get('/robots.txt')->assertOk();

    expect(trim($response->getContent()))
        ->toContain("User-agent: *\nDisallow: /")
        ->not->toContain('Sitemap:');
});

it('serves the sitemap while indexing is on and withdraws it when indexing is off', function () {
    $this->get('/sitemap.xml')->assertOk();

    Setting::set('seo_indexing_enabled', false);

    $this->get('/sitemap.xml')->assertNotFound();
});

it('does not put the admin panel under the public indexing setting', function () {
    Setting::set('seo_indexing_enabled', false);

    $this->get('/admin/login')
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag');
});
