<?php

use App\Models\Setting;

it('returns the default when a setting has never been set', function () {
    expect(Setting::get('contact_phone', 'fallback'))->toBe('fallback');
});

it('stores and retrieves a setting value', function () {
    Setting::set('contact_phone', '+91 90000 00000');

    expect(Setting::get('contact_phone'))->toBe('+91 90000 00000');
});

it('invalidates the cache when a setting is updated, so a stale value is never read back', function () {
    Setting::set('contact_email', 'old@fynnedge.com');
    expect(Setting::get('contact_email'))->toBe('old@fynnedge.com');

    Setting::set('contact_email', 'new@fynnedge.com');

    expect(Setting::get('contact_email'))->toBe('new@fynnedge.com');
});
