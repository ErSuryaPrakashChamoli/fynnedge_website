<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Models\Page;

it('renders the homepage', function () {
    $this->get('/')->assertOk()->assertSee('Simplifying loans');
});

it('renders the loans index with only published products', function () {
    $published = LoanProduct::factory()->published()->create(['name' => 'Home Loan', 'category' => LoanCategory::HomeLoan]);
    $draft = LoanProduct::factory()->create(['name' => 'Draft Product', 'status' => PublishStatus::Draft]);

    $response = $this->get('/loans');

    $response->assertOk();
    $response->assertSee($published->name);
    $response->assertDontSee($draft->name);
});

it('renders a published loan product by slug', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'business-loan']);

    $this->get('/loans/business-loan')->assertOk()->assertSee($product->name);
});

it('404s for a draft loan product on the public site', function () {
    LoanProduct::factory()->create(['slug' => 'draft-product', 'status' => PublishStatus::Draft]);

    $this->get('/loans/draft-product')->assertNotFound();
});

it('renders the about page from a published CMS page', function () {
    Page::factory()->published()->create(['slug' => 'about', 'title' => 'About FynnEdge']);

    $this->get('/about')->assertOk()->assertSee('About FynnEdge');
});

it('404s the about page when no about content is published', function () {
    Page::query()->delete();

    $this->get('/about')->assertNotFound();
});

it('renders the EMI calculator page', function () {
    $this->get('/calculators')->assertOk()->assertSee('EMI Calculator');
});
