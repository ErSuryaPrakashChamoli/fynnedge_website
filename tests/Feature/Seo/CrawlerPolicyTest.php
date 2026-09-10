<?php

use App\Models\Setting;
use App\Support\Seo\CrawlerPolicy;

function robotsTxt(): string
{
    return test()->get('/robots.txt')->assertOk()->getContent();
}

it('allows AI and answer-engine crawlers by default', function () {
    $robots = robotsTxt();

    foreach (array_keys(CrawlerPolicy::AI_CRAWLERS) as $crawler) {
        expect($robots)->toContain("User-agent: {$crawler}");
    }

    expect($robots)->not->toContain('Disallow: /'."\n".'User-agent');
    expect(substr_count($robots, 'Disallow: /'."\n"))->toBe(0);
});

it('disallows only the AI crawlers when an admin opts out, leaving search engines alone', function () {
    Setting::set('robots_ai_crawlers_allowed', false);

    $robots = robotsTxt();

    foreach (array_keys(CrawlerPolicy::AI_CRAWLERS) as $crawler) {
        expect($robots)->toMatch('/User-agent: '.preg_quote($crawler, '/').'\s*\nDisallow: \//');
    }

    foreach (CrawlerPolicy::SEARCH_CRAWLERS as $crawler) {
        expect($robots)->toMatch('/User-agent: '.preg_quote($crawler, '/').'\s*\nAllow: \//');
    }

    // The catch-all group and the sitemap are untouched by an AI opt-out.
    expect($robots)->toContain('Sitemap: '.route('sitemap'));
});

it('always disallows the private and transactional routes', function () {
    $robots = robotsTxt();

    foreach (CrawlerPolicy::disallowedPaths() as $path) {
        expect($robots)->toContain("Disallow: {$path}");
    }
});

it('appends admin-authored directives but drops lines that are not valid robots.txt', function () {
    Setting::set('robots_extra_directives', implode("\n", [
        '# campaign landing pages',
        'User-agent: SomeBot',
        'Disallow: /private-campaign',
        'this line is not a directive',
        '<script>alert(1)</script>',
    ]));

    $robots = robotsTxt();

    expect($robots)
        ->toContain('User-agent: SomeBot')
        ->toContain('Disallow: /private-campaign')
        ->toContain('# campaign landing pages')
        ->not->toContain('this line is not a directive')
        ->not->toContain('<script>');
});

it('collapses to a single blanket disallow when indexing is switched off, whatever the crawler policy says', function () {
    Setting::set('robots_ai_crawlers_allowed', true);
    Setting::set('seo_indexing_enabled', false);

    $robots = robotsTxt();

    expect(trim($robots))->toContain("User-agent: *\nDisallow: /")
        ->not->toContain('GPTBot')
        ->not->toContain('Sitemap:');
});
