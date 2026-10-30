<?php

use App\Models\Redirect;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * A small live site that passes every check: Googlebot's own robots.txt group
 * disallows the language switch, the sitemap lists two pages that answer 200
 * with their own canonical, and each hostname variant takes one 301. Its
 * pages carry an Apply link that is deliberately NOT faked: stray requests
 * are refused, so the audit fails loudly if it ever requests a link it finds.
 *
 * The redirect rows the legacy-URL migration ships are removed, so a test
 * audits only the rows it creates.
 *
 * @param  array<string, array{0?: string, 1?: int, 2?: array<string, string>}>  $overrides  URL => [body, status, headers]
 */
function fakeAuditedSite(array $overrides = []): void
{
    Redirect::query()->each(fn (Redirect $redirect) => $redirect->delete());

    $page = fn (string $url): array => [
        '<html><head><link rel="canonical" href="'.$url.'"><meta name="robots" content="index, follow"></head>'
        .'<body><a href="/loans/personal-loan/apply">Apply</a><form method="POST" action="/language/hi"></form></body></html>',
    ];
    $hostMove = fn (string $path): array => ['', 301, ['Location' => "https://site.test{$path}"]];

    $answers = [
        'https://site.test/robots.txt' => ["User-agent: Googlebot\nUser-agent: *\nAllow: /\nDisallow: /language/\n\nSitemap: https://site.test/sitemap.xml\n"],
        'https://site.test/sitemap.xml' => ['<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://site.test/loans</loc></url><url><loc>https://site.test/about</loc></url></urlset>'],
        'https://site.test/loans' => $page('https://site.test/loans'),
        'https://site.test/about' => $page('https://site.test/about'),
        'http://site.test/' => $hostMove('/'),
        'http://site.test/loans' => $hostMove('/loans'),
        'http://www.site.test/' => $hostMove('/'),
        'http://www.site.test/loans' => $hostMove('/loans'),
        'https://www.site.test/' => $hostMove('/'),
        'https://www.site.test/loans' => $hostMove('/loans'),
        ...$overrides,
    ];

    Http::preventStrayRequests();
    Http::fake(array_map(fn (array $answer) => Http::response(...$answer), $answers));
}

function auditSite(): int
{
    return Artisan::call('seo:audit', ['--base-url' => 'https://site.test']);
}

it('passes a site whose robots.txt, sitemap, pages and hostnames are all in order', function () {
    fakeAuditedSite();

    $exitCode = auditSite();

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No problems found.');
});

it('fails and names the problem the live site has', function (string $url, array $answer, string $problem) {
    fakeAuditedSite([$url => $answer]);

    $exitCode = auditSite();

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($problem);
})->with([
    'a static robots.txt that gives Googlebot its own Allow group' => [
        'https://site.test/robots.txt',
        ["User-agent: Googlebot\nAllow: /\n\nUser-agent: *\nDisallow: /language/\n\nSitemap: https://site.test/sitemap.xml\n"],
        'Googlebot\'s group does not disallow /language/',
    ],
    'a robots.txt with no Sitemap line' => [
        'https://site.test/robots.txt',
        ["User-agent: *\nDisallow: /language/\n"],
        'no Sitemap: line for https://site.test/sitemap.xml',
    ],
    'a sitemap that is not XML' => [
        'https://site.test/sitemap.xml',
        ['<html>not a sitemap</html>'],
        'is not a well-formed <urlset> document',
    ],
    'a sitemap URL that redirects' => [
        'https://site.test/about',
        ['', 301, ['Location' => 'https://site.test/about-us']],
        'redirects (301) to https://site.test/about-us',
    ],
    'a sitemap URL that is gone' => [
        'https://site.test/about',
        ['', 404],
        'answers 404',
    ],
    'a canonical pointing at another page' => [
        'https://site.test/about',
        ['<html><head><link rel="canonical" href="https://site.test/"></head></html>'],
        'canonical points at https://site.test/',
    ],
    'a sitemap page that says noindex' => [
        'https://site.test/about',
        ['<html><head><link rel="canonical" href="https://site.test/about"><meta name="robots" content="noindex, follow"></head></html>'],
        'is in the sitemap but says noindex',
    ],
    'a crawlable language-switch link' => [
        'https://site.test/about',
        ['<html><head><link rel="canonical" href="https://site.test/about"></head><body><a href="/language/hi?return=/about">हिन्दी</a></body></html>'],
        'is a crawlable language-switch link',
    ],
    'a www host the origin cannot serve' => [
        'http://www.site.test/',
        ['', 522],
        'expected a 301 to https://site.test/, got answers 522',
    ],
    'a hostname move in two hops' => [
        'http://www.site.test/loans',
        ['', 301, ['Location' => 'https://www.site.test/loans']],
        'redirects to https://www.site.test/loans, not straight to https://site.test/loans',
    ],
]);

it('flags a redirect row the live site does not send in one hop to a working page', function (array $sourceAnswer, array $landingAnswer, string $problem) {
    fakeAuditedSite([
        'https://site.test/old-rates' => $sourceAnswer,
        'https://site.test/rates' => $landingAnswer,
    ]);
    Redirect::factory()->create(['source_path' => '/old-rates', 'destination' => '/rates']);

    $exitCode = auditSite();

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($problem);
})->with([
    'served by no redirect at all' => [['', 404], [''], 'expected a redirect to https://site.test/rates, got answers 404'],
    'sent somewhere else' => [['', 301, ['Location' => 'https://site.test/']], [''], 'goes to https://site.test/, expected https://site.test/rates'],
    'landing on another redirect' => [['', 301, ['Location' => 'https://site.test/rates']], ['', 301, ['Location' => 'https://site.test/loans']], 'chain: https://site.test/rates redirects again'],
    'landing on a missing page' => [['', 301, ['Location' => 'https://site.test/rates']], ['', 404], 'destination https://site.test/rates answers 404'],
]);

it('refuses a base URL that is not an http(s) address', function () {
    Http::preventStrayRequests();

    $exitCode = Artisan::call('seo:audit', ['--base-url' => 'ftp://site.test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Give --base-url as a full http(s) URL');
});
