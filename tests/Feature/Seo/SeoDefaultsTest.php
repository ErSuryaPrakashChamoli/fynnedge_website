<?php

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Setting;
use App\Support\Seo\SeoDefaults;

function articleWithSeo(array $seo = []): Article
{
    $article = Article::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    if ($seo !== []) {
        $article->seoMeta()->create($seo);
    }

    return $article->fresh();
}

it('falls back to the built-in description when no default is configured', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('content="'.e(SeoDefaults::DEFAULT_DESCRIPTION).'"', false);
});

it('uses the admin-configured default meta description sitewide', function () {
    Setting::set('seo_meta_description', 'Compare lenders in minutes with FynnEdge.');

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="description" content="Compare lenders in minutes with FynnEdge.">', false);
});

it('mirrors the page title and description into Open Graph and Twitter by default', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="FynnEdge">')
        ->toContain('<meta name="twitter:title" content="FynnEdge">')
        ->toContain('content="'.e(SeoDefaults::DEFAULT_DESCRIPTION).'"');
});

it('uses the sitewide social defaults on a page that sets none of its own', function () {
    Setting::set('seo_default_og_title', 'FynnEdge — loans, simplified');
    Setting::set('seo_default_og_description', 'Upfront eligibility before you apply.');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="FynnEdge — loans, simplified">')
        ->toContain('<meta property="og:description" content="Upfront eligibility before you apply.">')
        // Twitter follows Open Graph unless it is given its own value.
        ->toContain('<meta name="twitter:title" content="FynnEdge — loans, simplified">');
});

it('never lets a sitewide social default override a page that has its own title', function () {
    Setting::set('seo_default_og_title', 'Sitewide title');
    Setting::set('seo_default_og_description', 'Sitewide description');
    $article = articleWithSeo(['title' => 'The article title', 'description' => 'The article description']);

    $html = $this->get(route('resources.show', $article))->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="The article title">')
        ->toContain('<meta property="og:description" content="The article description">')
        ->not->toContain('Sitewide title')
        ->not->toContain('Sitewide description');
});

it('lets a page override the sitewide social defaults', function () {
    Setting::set('seo_default_og_title', 'Sitewide title');
    $article = articleWithSeo([
        'og_title' => 'Page share title',
        'og_description' => 'Page share description',
        'twitter_title' => 'Page X title',
        'twitter_description' => 'Page X description',
    ]);

    $html = $this->get(route('resources.show', $article))->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="Page share title">')
        ->toContain('<meta property="og:description" content="Page share description">')
        ->toContain('<meta name="twitter:title" content="Page X title">')
        ->toContain('<meta name="twitter:description" content="Page X description">')
        ->not->toContain('Sitewide title');
});

it('chooses the twitter card type from the image unless an admin pins one', function () {
    expect(SeoDefaults::twitterCard('https://example.test/image.png'))->toBe('summary_large_image')
        ->and(SeoDefaults::twitterCard(null))->toBe('summary');

    Setting::set('seo_twitter_card', 'summary');
    expect(SeoDefaults::twitterCard('https://example.test/image.png'))->toBe('summary');

    Setting::set('seo_twitter_card', 'not-a-card');
    expect(SeoDefaults::twitterCard(null))->toBe('summary');
});

it('renders no image or description tags rather than empty ones', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->not->toContain('property="og:image" content=""')
        ->not->toContain('name="twitter:image" content=""');
});

it('canonicalises onto the configured base URL, and a page canonical always wins', function () {
    expect(SeoDefaults::canonical(null, 'http://internal.test/loans?a=1'))->toBe('http://internal.test/loans?a=1');

    Setting::set('seo_canonical_base_url', 'https://fynnedge.com');

    expect(SeoDefaults::canonical(null, 'http://internal.test/loans?a=1'))->toBe('https://fynnedge.com/loans?a=1')
        ->and(SeoDefaults::canonical('https://elsewhere.test/page', 'http://internal.test/loans'))->toBe('https://elsewhere.test/page');
});

it('ignores a malformed canonical base URL instead of emitting a broken canonical', function () {
    Setting::set('seo_canonical_base_url', 'not a url');

    expect(SeoDefaults::canonical(null, 'http://internal.test/loans'))->toBe('http://internal.test/loans');
});

it('emits exactly one canonical, robots, og:title and twitter:card tag per page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach (['rel="canonical"', 'name="robots"', 'property="og:title"', 'name="twitter:card"'] as $tag) {
        expect(substr_count($html, $tag))->toBe(1, "duplicate {$tag}");
    }
});
