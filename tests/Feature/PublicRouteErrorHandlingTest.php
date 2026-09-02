<?php

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;

/**
 * Phase 6.7 audit: a non-existent slug/id on any public content route must
 * 404 cleanly via Laravel's normal route-model-binding failure, never a 500
 * or an information-disclosing error page.
 */
it('404s cleanly for a non-existent loan product slug', function () {
    $this->get('/loans/this-slug-does-not-exist')->assertNotFound();
});

it('404s cleanly for a non-existent article slug', function () {
    $this->get('/resources/this-slug-does-not-exist')->assertNotFound();
});

it('404s cleanly for a non-existent static page slug', function () {
    $this->get('/this-page-does-not-exist')->assertNotFound();
});

it('404s cleanly for a non-existent loan landing page', function () {
    LoanProduct::factory()->published()->create(['slug' => 'real-product']);

    $this->get('/loans/real-product/this-landing-page-does-not-exist')->assertNotFound();
});

it('404s cleanly for a loan landing page under the wrong parent product', function () {
    $productA = LoanProduct::factory()->published()->create(['slug' => 'product-a']);
    $productB = LoanProduct::factory()->published()->create(['slug' => 'product-b']);
    $landingPage = $productA->landingPages()->create([
        'group' => LandingPageGroup::ByNeed,
        'title' => 'A Landing Page',
        'slug' => 'a-landing-page',
        'status' => PublishStatus::Published,
    ]);

    $this->get('/loans/product-b/a-landing-page')->assertNotFound();
});

it('does not leak a stack trace or debug information on a 404', function () {
    config(['app.debug' => false]);

    $response = $this->get('/loans/this-slug-does-not-exist');

    $response->assertNotFound();
    $response->assertDontSee('vendor/laravel', false);
    $response->assertDontSee('Stack trace', false);
});
