<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;

/**
 * SimpleXMLElement is not a normal iterable for collect() — collect() reads it
 * as a property bag and collapses every <url> into one key. Iterate it directly.
 *
 * @return array<int, string>
 */
function sitemapLocs(): array
{
    $xml = simplexml_load_string(test()->get('/sitemap.xml')->assertOk()->getContent());
    $locs = [];

    foreach ($xml->url as $url) {
        $locs[] = (string) $url->loc;
    }

    return $locs;
}

it('serves the sitemap as XML at the URL robots.txt advertises', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://fynnedge.com/sitemap.xml');
});

it('is valid, well-formed XML with a urlset root', function () {
    $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

    expect($xml)->not->toBeFalse();
    expect($xml->getName())->toBe('urlset');
});

it('lists the core public pages', function () {
    expect(sitemapLocs())->toContain(
        route('home'),
        route('loans.index'),
        route('about'),
        route('contact'),
        route('faqs.index'),
        route('resources.index'),
        route('calculators.index'),
    );
});

it('lists published loan products and excludes drafts and expired ones', function () {
    $live = LoanProduct::factory()->published()->create(['slug' => 'sitemap-live']);
    $draft = LoanProduct::factory()->create(['slug' => 'sitemap-draft', 'status' => PublishStatus::Draft]);
    $expired = LoanProduct::factory()->published()->create(['slug' => 'sitemap-expired', 'expires_at' => now()->subDay()]);

    $locs = sitemapLocs();

    expect($locs)->toContain(route('loans.show', $live));
    expect($locs)->not->toContain(route('loans.show', $draft));
    expect($locs)->not->toContain(route('loans.show', $expired));
});

/**
 * Note: LoanLandingPageController only checks the landing page's own publish
 * state, so this URL currently still renders 200 even though its parent product
 * is a draft. The sitemap deliberately does not advertise it regardless — a
 * landing page for an unpublished product is not content that should be indexed.
 */
it('omits landing pages whose parent product is unpublished', function () {
    $draftProduct = LoanProduct::factory()->create(['slug' => 'hidden-parent', 'status' => PublishStatus::Draft]);
    $landingPage = LoanLandingPage::factory()->for($draftProduct, 'loanProduct')->create([
        'slug' => 'orphan-landing',
        'status' => PublishStatus::Published,
        'published_at' => now(),
    ]);

    $url = route('loans.landing-pages.show', ['loanProduct' => $draftProduct, 'landingPage' => $landingPage]);

    expect(sitemapLocs())->not->toContain($url);
});

it('lists published articles and routed legal pages', function () {
    $article = Article::factory()->published()->create(['slug' => 'sitemap-article']);
    Page::factory()->create(['slug' => 'terms', 'status' => PublishStatus::Published, 'published_at' => now()]);

    expect(sitemapLocs())->toContain(route('resources.show', $article), route('terms'));
});

it('never exposes admin, funnel or non-routed CMS pages', function () {
    Page::factory()->create(['slug' => 'about', 'status' => PublishStatus::Published, 'published_at' => now()]);

    $locs = sitemapLocs();

    foreach ($locs as $loc) {
        expect($loc)->not->toContain('/admin');
        expect($loc)->not->toContain('/journey');
        expect($loc)->not->toContain('/applications');
        expect($loc)->not->toContain('/credit-score');
        expect($loc)->not->toContain('signature=');
    }

    // The `about` CMS row is served by AboutController at /about, listed once
    // as a static entry — it must not also appear as a second CMS-page URL.
    expect(collect($locs)->filter(fn (string $loc): bool => $loc === route('about')))->toHaveCount(1);
});

it('emits no duplicate URLs', function () {
    $locs = sitemapLocs();

    expect(count($locs))->toBe(count(array_unique($locs)));
});

it('only emits lastmod when the record actually tracks one', function () {
    LoanProduct::factory()->published()->create(['slug' => 'lastmod-check', 'category' => LoanCategory::PersonalLoan]);

    $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

    $home = null;
    $product = null;

    foreach ($xml->url as $url) {
        match (true) {
            (string) $url->loc === route('home') => $home = $url,
            str_ends_with((string) $url->loc, '/loans/lastmod-check') => $product = $url,
            default => null,
        };
    }

    expect($home->lastmod->count())->toBe(0);
    expect((string) $product->lastmod)->not->toBeEmpty();
});

it('allows AI answer engines and disallows private routes in robots.txt', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    foreach (['OAI-SearchBot', 'GPTBot', 'PerplexityBot', 'Google-Extended', 'Anthropic-AI', 'Applebot-Extended', 'Bingbot'] as $bot) {
        expect($robots)->toContain("User-agent: {$bot}");
    }

    foreach (['/admin', '/login', '/register', '/api', '/journey/', '/applications/', '/credit-score/'] as $path) {
        expect($robots)->toContain("Disallow: {$path}");
    }
});
