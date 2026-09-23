<?php

use App\Models\PageSeo;
use App\Models\SeoMeta;
use App\Support\Seo\PublicPagePaths;

it('renders the admin-set meta title and description on a page that has none of its own', function () {
    PageSeo::factory()->withMeta('/', 'Compare Loan Offers in India', 'Check your eligibility with 40+ lenders in two minutes.')->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Compare Loan Offers in India</title>', false)
        ->assertSee('<meta name="description" content="Check your eligibility with 40+ lenders in two minutes.">', false);
});

it('overrides the meta tags a view hardcodes for itself', function () {
    PageSeo::factory()->withMeta('/contact', 'Talk to a FynnEdge Advisor', 'Call, email or visit our Dehradun office.')->create();

    $this->get('/contact')
        ->assertOk()
        ->assertSee('<title>Talk to a FynnEdge Advisor</title>', false)
        ->assertSee('<meta name="description" content="Call, email or visit our Dehradun office.">', false)
        ->assertDontSee('Get in touch with FynnEdge Advisory.', false);
});

it('matches the request whatever casing, trailing slash or query string an admin typed', function () {
    PageSeo::factory()->withMeta('https://fynnedge.com/Contact/?utm_source=x', 'Normalised Match')->create();

    $this->get('/contact')
        ->assertOk()
        ->assertSee('<title>Normalised Match</title>', false);
});

it('leaves a blank field alone instead of blanking what the page already shows', function () {
    PageSeo::factory()->withMeta('/contact', 'Only The Title Is Set')->create();

    $this->get('/contact')
        ->assertOk()
        ->assertSee('<title>Only The Title Is Set</title>', false)
        ->assertSee('<meta name="description" content="Get in touch with FynnEdge Advisory.">', false);
});

it('ignores an inactive entry', function () {
    PageSeo::factory()->inactive()->withMeta('/contact', 'Switched Off')->create();

    $this->get('/contact')
        ->assertOk()
        ->assertDontSee('Switched Off', false)
        ->assertSee('<title>Contact</title>', false);
});

it('applies the canonical, robots and social overrides from the same entry', function () {
    $pageSeo = PageSeo::factory()->create(['url_path' => '/faqs']);
    $pageSeo->seoMeta()->save(new SeoMeta([
        'title' => 'Questions, Answered',
        'canonical_url' => 'https://fynnedge.com/faqs',
        'robots' => 'noindex, follow',
        'og_title' => 'Every FynnEdge FAQ',
    ]));

    $this->get('/faqs')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://fynnedge.com/faqs">', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<meta property="og:title" content="Every FynnEdge FAQ">', false);
});

it('leaves every other page untouched', function () {
    PageSeo::factory()->withMeta('/contact', 'Only Contact Changes')->create();

    $this->get('/faqs')
        ->assertOk()
        ->assertDontSee('Only Contact Changes', false);
});

it('suggests the fixed public pages and never the admin panel', function () {
    $paths = PublicPagePaths::all();

    expect($paths)->toContain('/', '/contact', '/faqs', '/quick-enquiry', '/calculators')
        ->not->toContain('/sitemap.xml');

    expect(collect($paths)->filter(fn (string $path) => str_starts_with($path, '/admin')))->toBeEmpty();
});

it('renders the admin-set title exactly, without appending the site name', function () {
    PageSeo::factory()->withMeta('/contact', 'Personal Loan – Apply Online')->create();

    $this->get('/contact')
        ->assertOk()
        ->assertSee('<title>Personal Loan – Apply Online</title>', false)
        ->assertDontSee('Personal Loan – Apply Online — FynnEdge', false);
});
