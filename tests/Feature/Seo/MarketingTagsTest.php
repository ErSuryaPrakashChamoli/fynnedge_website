<?php

use App\Models\Setting;

function siteHtml(): string
{
    return test()->get('/')->assertOk()->getContent();
}

it('renders no marketing or analytics platform tags when nothing is configured', function () {
    expect(siteHtml())
        ->not->toContain('clarity.ms')
        ->not->toContain('connect.facebook.net')
        ->not->toContain('snap.licdn.com');
});

it('renders Microsoft Clarity once from a valid project ID', function () {
    Setting::set('microsoft_clarity_project_id', 'abcd12345');

    $html = siteHtml();

    expect($html)->toContain("'abcd12345'")
        ->and(substr_count($html, 'fynnedgeClarityLoaded = true'))->toBe(1)
        ->and(substr_count($html, 'clarity.ms/tag/'))->toBe(1);
});

it('renders the Meta Pixel script and its noscript fallback once each', function () {
    Setting::set('meta_pixel_id', '123456789012345');

    $html = siteHtml();

    expect(substr_count($html, 'fynnedgeMetaPixelLoaded = true'))->toBe(1)
        ->and(substr_count($html, 'facebook.com/tr?id=123456789012345'))->toBe(1)
        ->and($html)->toContain('connect.facebook.net/en_US/fbevents.js');

    expect(strpos($html, 'facebook.com/tr?id='))->toBeLessThan(strpos($html, 'Skip to content'));
});

it('renders the LinkedIn Insight tag from a partner ID', function () {
    Setting::set('linkedin_partner_id', '1234567');

    $html = siteHtml();

    expect(substr_count($html, 'fynnedgeLinkedInLoaded = true'))->toBe(1)
        ->and($html)->toContain('snap.licdn.com/li.lms-analytics/insight.min.js')
        ->and($html)->toContain('px.ads.linkedin.com/collect/?pid=1234567');
});

it('renders nothing for a malformed platform ID', function (string $key, string $value, string $marker) {
    Setting::set($key, $value);

    expect(siteHtml())->not->toContain($marker);
})->with([
    ['microsoft_clarity_project_id', '"><script>', 'clarity.ms'],
    ['meta_pixel_id', 'not-a-number', 'connect.facebook.net'],
    ['linkedin_partner_id', 'abc', 'snap.licdn.com'],
    ['google_ads_conversion_id', 'AW-abc', 'gtag/js'],
]);

it('loads one shared gtag loader when GA4 and Google Ads are both configured', function () {
    Setting::set('google_analytics_enabled', true);
    Setting::set('google_analytics_measurement_id', 'G-ABCD123456');
    Setting::set('google_ads_conversion_id', 'AW-123456789');

    $html = siteHtml();

    expect(substr_count($html, 'gtag/js'))->toBe(1)
        ->and(substr_count($html, 'fynnedgeGtagLoaded = true'))->toBe(1)
        ->and($html)->toContain("w.gtag('config', 'G-ABCD123456');")
        ->and($html)->toContain("w.gtag('config', 'AW-123456789');");
});

it('loads Google Ads on its own when GA4 is disabled', function () {
    Setting::set('google_ads_conversion_id', 'AW-123456789');

    $html = siteHtml();

    expect($html)->toContain('gtag/js')
        ->and($html)->toContain("w.gtag('config', 'AW-123456789');")
        ->and($html)->not->toContain('G-');
});

it('renders each verification tag only when its field is filled', function () {
    expect(siteHtml())
        ->not->toContain('msvalidate.01')
        ->not->toContain('facebook-domain-verification')
        ->not->toContain('p:domain_verify');

    Setting::set('bing_webmaster_verification', 'ABCDEF1234567890ABCDEF1234567890');
    Setting::set('facebook_domain_verification', 'abcdefghij1234567890');
    Setting::set('pinterest_verification', 'zyxwvutsrq0987654321');

    expect(siteHtml())
        ->toContain('<meta name="msvalidate.01" content="ABCDEF1234567890ABCDEF1234567890">')
        ->toContain('<meta name="facebook-domain-verification" content="abcdefghij1234567890">')
        ->toContain('<meta name="p:domain_verify" content="zyxwvutsrq0987654321">');
});

it('accepts a pasted Bing meta tag and keeps only the token', function () {
    Setting::set('bing_webmaster_verification', '<meta name="msvalidate.01" content="ABCDEF1234567890" />');

    expect(siteHtml())->toContain('<meta name="msvalidate.01" content="ABCDEF1234567890">');
});

it('renders extra verification meta tags but drops anything that is not a meta tag', function () {
    Setting::set('other_verification_meta', implode("\n", [
        '<meta name="yandex-verification" content="abc123">',
        '<script>alert(1)</script>',
        '<meta name="evil" content="x" onload="alert(1)">',
    ]));

    $html = siteHtml();

    expect($html)->toContain('<meta name="yandex-verification" content="abc123">')
        ->not->toContain('alert(1)');
});

it('injects custom CSS and JavaScript in the right places for a super admin setting', function () {
    Setting::set('custom_css', '.custom-marker { color: red; }');
    Setting::set('custom_javascript', 'window.customMarker = true;');

    $html = siteHtml();

    expect($html)->toContain('<style>.custom-marker { color: red; }</style>')
        ->and($html)->toContain('<script>window.customMarker = true;</script>');

    expect(strpos($html, '.custom-marker'))->toBeLessThan(strpos($html, '</head>'));
    expect(strpos($html, 'window.customMarker'))->toBeGreaterThan(strpos($html, '</main>'));
});
