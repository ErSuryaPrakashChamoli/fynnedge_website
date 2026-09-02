<?php

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;

/**
 * Phase 7 content-consistency audit: LoanProduct already lets an admin
 * override its "Check Your Eligibility" CTA label; LoanLandingPage had the
 * identical CTA (same route, same default text) with no such field — a real
 * inconsistency between two sibling content types. Fixed by mirroring the
 * exact same label-only-override pattern; the destination stays the real
 * business route regardless.
 */
it('lets a custom CTA label override the default text on a loan landing page', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'cta-consistency-product']);
    $landingPage = $loanProduct->landingPages()->create([
        'group' => LandingPageGroup::ByNeed,
        'title' => 'Custom CTA Landing Page',
        'slug' => 'custom-cta-landing-page',
        'cta_label' => 'Get Started Now',
        'status' => PublishStatus::Published,
    ]);

    $response = $this->get('/loans/cta-consistency-product/custom-cta-landing-page');

    $response->assertOk()
        ->assertSee('Get Started Now')
        ->assertDontSee('Check Your Eligibility');

    // The destination is still the real business journey route regardless of label.
    $response->assertSee(route('loans.apply', $loanProduct), false);
});

it('falls back to the original default CTA label when none is set', function () {
    $loanProduct = LoanProduct::factory()->published()->create(['slug' => 'cta-fallback-product']);
    $loanProduct->landingPages()->create([
        'group' => LandingPageGroup::ByNeed,
        'title' => 'Default CTA Landing Page',
        'slug' => 'default-cta-landing-page',
        'status' => PublishStatus::Published,
    ]);

    $this->get('/loans/cta-fallback-product/default-cta-landing-page')
        ->assertOk()
        ->assertSee('Check Your Eligibility');
});
