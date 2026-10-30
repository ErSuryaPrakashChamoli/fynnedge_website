<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

it('returns the default when a setting has never been set', function () {
    expect(Setting::get('contact_phone', 'fallback'))->toBe('fallback');
});

it('stores and retrieves a setting value', function () {
    Setting::set('contact_phone', '+91 90000 00000');

    expect(Setting::get('contact_phone'))->toBe('+91 90000 00000');
});

/*
 * forgetScopedInstances() starts a fresh request's memo, so these prove what
 * the shared cache holds between requests, not only within one.
 */
it('reads a setting that was never saved from the database once, not on every request', function () {
    $settingsQueries = fn (): int => collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'settings'))->count();
    DB::enableQueryLog();

    Setting::get('theme_header_bg_color');
    app()->forgetScopedInstances();
    Setting::get('theme_header_bg_color');

    expect($settingsQueries())->toBe(1);
});

it('gives each caller its own default for a setting that was never saved', function () {
    Setting::get('hero_heading', 'First default');
    app()->forgetScopedInstances();

    expect(Setting::get('hero_heading', 'Second default'))->toBe('Second default');
});

it('reads back a value saved after the setting was read while unset', function () {
    Setting::get('contact_phone');
    app()->forgetScopedInstances();

    Setting::set('contact_phone', '+91 90000 00000');
    app()->forgetScopedInstances();

    expect(Setting::get('contact_phone'))->toBe('+91 90000 00000');
});

it('invalidates the cache when a setting is updated, so a stale value is never read back', function () {
    Setting::set('contact_email', 'old@fynnedge.com');
    expect(Setting::get('contact_email'))->toBe('old@fynnedge.com');

    Setting::set('contact_email', 'new@fynnedge.com');

    expect(Setting::get('contact_email'))->toBe('new@fynnedge.com');
});
