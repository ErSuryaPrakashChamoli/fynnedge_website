<?php

use App\Models\Setting;
use App\Support\Privacy\CookieConsent;

beforeEach(function () {
    Setting::set('google_analytics_enabled', true);
    Setting::set('google_analytics_measurement_id', 'G-ABCD123456');
    Setting::set('meta_pixel_id', '123456789012345');
});

it('shows no banner and gates nothing while consent is switched off', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Cookie preferences')
        ->and($html)->toContain('gtag/js')
        ->and($html)->toContain('connect.facebook.net');
});

it('shows the banner once enabled, without gating categories that are not marked as requiring consent', function () {
    Setting::set('cookie_consent_enabled', true);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Cookie preferences')
        ->and($html)->toContain('Accept all')
        ->and($html)->toContain('Essential only')
        // Neither category was marked as requiring consent, so nothing is blocked.
        ->and($html)->toContain('gtag/js')
        ->and($html)->toContain('connect.facebook.net');
});

it('keeps gated scripts out of the HTML entirely until the visitor consents', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('analytics_consent_required', true);
    Setting::set('marketing_consent_required', true);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Cookie preferences')
        ->and($html)->not->toContain('gtag/js')
        ->and($html)->not->toContain('connect.facebook.net');
});

it('renders the consented categories on the next request and stops prompting', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('analytics_consent_required', true);
    Setting::set('marketing_consent_required', true);

    $html = $this->withUnencryptedCookie(CookieConsent::COOKIE, 'analytics')
        ->get('/')->assertOk()->getContent();

    expect($html)->toContain('gtag/js')
        ->and($html)->not->toContain('connect.facebook.net')
        ->and($html)->not->toContain('Cookie preferences');
});

it('treats an "essential only" answer as a refusal, not as an unanswered banner', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('analytics_consent_required', true);

    $html = $this->withUnencryptedCookie(CookieConsent::COOKIE, 'none')
        ->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('gtag/js')
        ->and($html)->not->toContain('Cookie preferences');
});

it('ignores an unknown category in the cookie', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('analytics_consent_required', true);

    $this->withUnencryptedCookie(CookieConsent::COOKIE, 'everything,admin')->get('/');

    expect(CookieConsent::grantedCategories())->toBe([]);
});

it('gates the advanced custom scripts under marketing consent', function () {
    Setting::set('custom_head_scripts', '<script>window.customTag = 1;</script>');
    Setting::set('cookie_consent_enabled', true);
    Setting::set('marketing_consent_required', true);

    expect($this->get('/')->getContent())->not->toContain('window.customTag');

    expect($this->withUnencryptedCookie(CookieConsent::COOKIE, 'marketing')->get('/')->getContent())
        ->toContain('window.customTag');
});

it('shows the configured policy links in the banner', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('privacy_policy_url', 'https://fynnedge.com/privacy-policy');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('https://fynnedge.com/privacy-policy')
        ->and($html)->toContain('Privacy policy')
        ->and($html)->not->toContain('>Cookie policy<');
});

it('keeps the consent cookie unencrypted so the banner can write it from JavaScript', function () {
    Setting::set('cookie_consent_enabled', true);
    Setting::set('analytics_consent_required', true);

    // A plain, unencrypted cookie value must be readable back by the app —
    // if it were in the encrypted list, this would silently read as absent.
    $html = $this->withUnencryptedCookie(CookieConsent::COOKIE, 'analytics')->get('/')->getContent();

    expect($html)->toContain('gtag/js');
});
