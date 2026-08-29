<?php

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
    expect($csp)->not->toContain('http://');
    expect($csp)->not->toContain('https://cdn');
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
