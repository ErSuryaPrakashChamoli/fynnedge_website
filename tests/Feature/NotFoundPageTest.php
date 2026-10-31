<?php

use App\Models\Redirect;
use App\Support\Localization\SiteLocale;

it('renders the branded page with the main sections linked and keeps the 404 status', function () {
    $response = $this->get('/no-such-page-anywhere');

    $response->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('id="main-content"', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('href="'.route('loans.index').'"', false)
        ->assertSee('href="'.route('calculators.index').'"', false)
        ->assertSee('href="'.route('resources.index').'"', false)
        ->assertSee('href="'.route('contact').'"', false);
});

it('shows no exception details for a missing record even with debug on', function () {
    config(['app.debug' => true]);

    $response = $this->get('/resources/this-article-does-not-exist');

    $response->assertNotFound()
        ->assertSee('Page not found')
        ->assertDontSee('No query results')
        ->assertDontSee('NotFoundHttpException')
        ->assertDontSee(base_path(), false);
});

it('answers a scanner probe or a stale asset URL with the bare 404, not the branded page', function (string $path) {
    $response = $this->get($path);

    $response->assertNotFound()
        ->assertSee('Not Found')
        ->assertDontSee('id="main-content"', false);
})->with([
    'WordPress login probe' => '/wp-login.php',
    'environment file probe' => '/.env',
    'old build asset' => '/build/assets/app-0ld.js',
    'missing image' => '/images/old-logo.png',
]);

it('keeps a JSON request for a probe path a JSON 404', function () {
    $this->getJson('/wp-login.php')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('shows a Gone rule\'s 410 page in the visitor\'s language', function () {
    Redirect::factory()->gone()->create(['source_path' => '/retired-article']);

    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get('/retired-article')
        ->assertGone()
        ->assertSee('यह पेज हटा दिया गया है')
        ->assertDontSee('This page has been removed');
});
