<?php

use App\Models\Setting;

function trackedHomepageHtml(): string
{
    return test()->get('/')->assertOk()->getContent();
}

it('renders no analytics, tag manager or verification tag on a site with nothing configured', function () {
    $html = trackedHomepageHtml();

    expect($html)
        ->not->toContain('googletagmanager.com')
        ->not->toContain('google-site-verification')
        ->not->toContain('dataLayer');
});

it('does not load GA4 while the toggle is off, even with a measurement ID saved', function () {
    Setting::set('google_analytics_measurement_id', 'G-ABCD123456');
    Setting::set('google_analytics_enabled', false);

    expect(trackedHomepageHtml())->not->toContain('gtag/js');
});

it('loads GA4 exactly once when enabled with a valid measurement ID', function () {
    Setting::set('google_analytics_enabled', true);
    Setting::set('google_analytics_measurement_id', 'G-ABCD123456');

    $html = trackedHomepageHtml();

    expect($html)->toContain('gtag/js')
        ->and($html)->toContain("'G-ABCD123456'")
        ->and(substr_count($html, 'fynnedgeGtagLoaded = true'))->toBe(1);
});

it('does not load GA4 when enabled with an empty or malformed measurement ID', function (?string $id) {
    Setting::set('google_analytics_enabled', true);
    Setting::set('google_analytics_measurement_id', $id);

    expect(trackedHomepageHtml())->not->toContain('gtag/js');
})->with([null, '', 'UA-12345-1', 'G-<script>alert(1)</script>', 'not-an-id']);

it('does not load GTM while the toggle is off, even with a container ID saved', function () {
    Setting::set('google_tag_manager_container_id', 'GTM-ABC1234');
    Setting::set('google_tag_manager_enabled', false);

    expect(trackedHomepageHtml())->not->toContain('gtm.js');
});

it('renders the GTM script in the head and its noscript right after the body opens', function () {
    Setting::set('google_tag_manager_enabled', true);
    Setting::set('google_tag_manager_container_id', 'GTM-ABC1234');

    $html = trackedHomepageHtml();

    expect($html)->toContain('gtm.js')
        ->and($html)->toContain('https://www.googletagmanager.com/ns.html?id=GTM-ABC1234')
        ->and(substr_count($html, 'fynnedgeGtmLoaded = true'))->toBe(1)
        ->and(substr_count($html, 'ns.html?id=GTM-ABC1234'))->toBe(1);

    // The noscript iframe has to be the first thing inside <body>, before the skip link.
    expect(strpos($html, 'ns.html?id=GTM-ABC1234'))
        ->toBeGreaterThan(strpos($html, '<body'))
        ->toBeLessThan(strpos($html, 'Skip to content'));

    // ...and the script itself has to be in the head, before it.
    expect(strpos($html, 'gtm.js'))->toBeLessThan(strpos($html, '</head>'));
});

it('does not load GTM when enabled with an empty or malformed container ID', function (?string $id) {
    Setting::set('google_tag_manager_enabled', true);
    Setting::set('google_tag_manager_container_id', $id);

    expect(trackedHomepageHtml())->not->toContain('gtm.js');
})->with([null, '', 'GTM', 'G-ABCD123456', 'GTM-"><script>']);

it('renders no verification tag when the search console field is empty', function () {
    Setting::set('google_search_console_verification', '');

    expect(trackedHomepageHtml())->not->toContain('google-site-verification');
});

it('renders the verification tag when the search console field is filled', function () {
    Setting::set('google_search_console_verification', 'abcDEF123456_ghiJKL-789');

    expect(trackedHomepageHtml())
        ->toContain('<meta name="google-site-verification" content="abcDEF123456_ghiJKL-789">');
});

it('ignores a verification token that is not a plausible search console token', function () {
    Setting::set('google_search_console_verification', '"><script>alert(1)</script>');

    expect(trackedHomepageHtml())->not->toContain('google-site-verification');
});

it('renders custom scripts unescaped at each of the three locations, once each', function () {
    Setting::set('custom_head_scripts', '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>');
    Setting::set('custom_body_start_scripts', '<noscript><img src="https://www.facebook.com/tr?id=1" /></noscript>');
    Setting::set('custom_body_end_scripts', '<script>window.clarity=1;</script>');

    $html = trackedHomepageHtml();

    expect(substr_count($html, 'connect.facebook.net/en_US/fbevents.js'))->toBe(1)
        ->and(substr_count($html, 'window.clarity=1;'))->toBe(1)
        ->and($html)->not->toContain('&lt;script&gt;');

    expect(strpos($html, 'fbevents.js'))->toBeLessThan(strpos($html, '</head>'));
    expect(strpos($html, 'facebook.com/tr?id=1'))->toBeLessThan(strpos($html, 'Skip to content'));
    expect(strpos($html, 'window.clarity=1;'))->toBeGreaterThan(strpos($html, '</main>'));
});

it('allows the origins the enabled tags need in the content security policy', function () {
    Setting::set('google_analytics_enabled', true);
    Setting::set('google_analytics_measurement_id', 'G-ABCD123456');
    Setting::set('google_tag_manager_enabled', true);
    Setting::set('google_tag_manager_container_id', 'GTM-ABC1234');
    Setting::set('custom_head_scripts', '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>');

    $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('script-src')
        ->toMatch('/script-src[^;]*https:\/\/www\.googletagmanager\.com/')
        ->toMatch('/script-src[^;]*https:\/\/connect\.facebook\.net/')
        ->toMatch('/frame-src[^;]*https:\/\/www\.googletagmanager\.com/')
        ->toMatch('/connect-src[^;]*https:\/\/\*\.google-analytics\.com/');
});

it('leaves the content security policy untouched while every tag is disabled', function () {
    $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("script-src 'self' 'unsafe-eval' 'unsafe-inline';")
        ->not->toContain('googletagmanager');
});
