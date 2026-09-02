<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

it('renders the default branding, tagline and hero copy when nothing has been customized', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSee('FynnEdge');
    $response->assertSee('Simplifying Loan, Amplifying Trust');
    $response->assertSee('Simplifying loans.');
    $response->assertSee('Amplifying trust.');
    $response->assertSee(asset('fynnedge-icon.png'), false);
});

it('reflects a custom site name and tagline in the header, footer and page title', function () {
    Setting::set('site_name', 'Acme Loans');
    Setting::set('site_tagline', 'Faster loans, fewer forms');

    $response = $this->get('/')->assertOk();

    $response->assertSee('<title>Acme Loans — Faster loans, fewer forms</title>', false);
    $response->assertSeeInOrder(['Acme Loans', 'Faster loans, fewer forms']);
    $response->assertDontSee('Simplifying Loan, Amplifying Trust');
});

it('reflects a custom logo upload in the header and footer', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/custom-logo.png', 'fake-image-content');
    Setting::set('site_logo', 'branding/custom-logo.png');

    $response = $this->get('/')->assertOk();

    $response->assertSee(Storage::disk('public')->url('branding/custom-logo.png'), false);
    $response->assertDontSee(asset('fynnedge-icon.png'), false);
});

it('reflects a custom favicon and drops the default multi-size favicon links', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/custom-favicon.png', 'fake-image-content');
    Setting::set('site_favicon', 'branding/custom-favicon.png');

    $response = $this->get('/')->assertOk();

    $response->assertSee(Storage::disk('public')->url('branding/custom-favicon.png'), false);
    $response->assertDontSee(asset('favicon-32x32.png'), false);
});

it('reflects custom homepage hero copy', function () {
    Setting::set('hero_eyebrow', 'Acme Loans Pvt Ltd');
    Setting::set('hero_heading', 'Borrow smarter.');
    Setting::set('hero_heading_accent', 'Live better.');
    Setting::set('hero_subheading', 'Acme connects you with lenders across every major loan category.');

    $this->get('/')
        ->assertOk()
        ->assertSee('Acme Loans Pvt Ltd')
        ->assertSee('Borrow smarter.')
        ->assertSee('Live better.')
        ->assertSee('Acme connects you with lenders across every major loan category.')
        ->assertDontSee('Simplifying loans.');
});

it('reflects a custom footer legal name and disclaimer', function () {
    Setting::set('footer_legal_name', 'Acme Loans Advisory Pvt Ltd');
    Setting::set('footer_disclaimer', 'All loans are subject to lender approval and underwriting.');

    $this->get('/')
        ->assertOk()
        ->assertSee('Acme Loans Advisory Pvt Ltd')
        ->assertSee('All loans are subject to lender approval and underwriting.')
        ->assertSee('&copy; '.now()->year.' Acme Loans Advisory Pvt Ltd. All rights reserved.', false);
});

it('falls back to the default sitewide social share image when a page has none of its own', function () {
    Storage::fake('public');
    Storage::disk('public')->put('seo/default-og.jpg', 'fake-image-content');
    Setting::set('seo_default_og_image', 'seo/default-og.jpg');

    $response = $this->get('/')->assertOk();

    $response->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('seo/default-og.jpg').'">', false);
});
