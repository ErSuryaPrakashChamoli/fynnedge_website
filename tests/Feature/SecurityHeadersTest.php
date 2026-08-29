<?php

use App\Http\Middleware\SecurityHeaders;

it('applies security headers to every response', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
});

it('applies a Content-Security-Policy that only trusts self-hosted assets', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeNull();
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("script-src 'self' 'unsafe-eval'");
    expect($csp)->toContain("style-src 'self' 'unsafe-inline'");
    expect($csp)->toContain("frame-ancestors 'none'");
    expect($csp)->not->toContain('https://cdn');
});

it('widens script/style/connect-src to the Vite dev server origin when public/hot is present', function () {
    $origins = SecurityHeaders::parseHotFileUrl('http://127.0.0.1:5173');

    expect($origins['http'])->toBe('http://127.0.0.1:5173');
    expect($origins['ws'])->toBe('ws://127.0.0.1:5173');
});

it('uses wss for the HMR websocket when the dev server URL is https', function () {
    $origins = SecurityHeaders::parseHotFileUrl('https://127.0.0.1:5173');

    expect($origins['ws'])->toBe('wss://127.0.0.1:5173');
});

it('adds nothing when the hot file is empty or unparseable', function () {
    expect(SecurityHeaders::parseHotFileUrl(''))->toBe(['http' => '', 'ws' => '']);
    expect(SecurityHeaders::parseHotFileUrl('not a url'))->toBe(['http' => '', 'ws' => '']);
});

it('sends the same Content-Security-Policy on the admin panel and a public page', function () {
    $publicCsp = $this->get('/')->headers->get('Content-Security-Policy');
    $adminCsp = $this->get('/admin/login')->headers->get('Content-Security-Policy');

    expect($adminCsp)->toBe($publicCsp);
});

it('still applies security headers to the login redirect an unauthenticated admin request becomes', function () {
    $response = $this->get('/admin');

    $response->assertRedirect();
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Content-Security-Policy');
});
