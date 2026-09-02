<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Phase 7, Step 10 — a single consolidated audit confirming the content
 * team's two roles (Marketing, SEO) can reach exactly the content surface
 * they need and nothing sensitive, in one place rather than scattered
 * across other test files.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets Marketing manage every content area the content team owns', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    foreach ([
        '/admin/articles', '/admin/banners', '/admin/testimonials', '/admin/company-photos',
        '/admin/loan-landing-page-content', '/admin/faqs', '/admin/marketing-sections',
        '/admin/navigation-links', '/admin/how-it-works-steps', '/admin/calculator-pages',
    ] as $path) {
        $this->get($path)->assertOk();
    }

    // Phase 9A: full /admin/loan-landing-pages access was replaced by the
    // narrower /admin/loan-landing-page-content boundary — see
    // LoanLandingPageAccessSeparationTest.
    $this->get('/admin/loan-landing-pages')->assertForbidden();
});

it('lets SEO manage exactly the SEO-relevant resources', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);
    $this->actingAs($user);

    // Phase 8: full /admin/loan-products access was replaced by the narrower
    // /admin/loan-product-seo boundary — see LoanProductSeoResourceTest.
    // Phase 9A: the same applies to /admin/loan-landing-pages, replaced by
    // /admin/loan-landing-page-seo.
    foreach (['/admin/articles', '/admin/loan-product-seo', '/admin/loan-landing-page-seo', '/admin/pages'] as $path) {
        $this->get($path)->assertOk();
    }

    $this->get('/admin/loan-products')->assertForbidden();
    $this->get('/admin/loan-landing-pages')->assertForbidden();
});

it('blocks Marketing from the full LoanLandingPage editor, since that form also contains loan_product_id, group, slug and amount', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    $this->get('/admin/loan-landing-pages')->assertForbidden();
});

it('blocks SEO from the LoanLandingPage content editor', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);
    $this->actingAs($user);

    $this->get('/admin/loan-landing-page-content')->assertForbidden();
});

it('blocks Marketing and SEO from every sensitive or business-critical area', function (string $role) {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([$role]);
    $this->actingAs($user);

    foreach ([
        '/admin/users',
        '/admin/shield/roles',
        '/admin/applications',
        '/admin/eligibility-rule-sets',
        '/admin/credit-score-checks',
        '/admin/customers',
        '/admin/lenders',
        '/admin/lender-products',
        '/admin/journey-definitions',
        '/admin/journey-sessions',
        '/admin/employers',
        '/admin/document-types',
        '/admin/document-requirements',
        '/admin/media-governance',
        '/admin/admin-activity',
    ] as $path) {
        $this->get($path)->assertForbidden();
    }
})->with(['Marketing', 'SEO']);

it('documents that Marketing has no LoanProduct access at all, since that form is shared with business-critical calculator fields', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    // This is intentional, not an oversight — see Phase 7 report "Known
    // Limitations". LoanProduct's marketing fields (headline, benefits, image,
    // CTA label) and its business fields (min/max amount, tenure, interest
    // rate feeding the EMI calculator) live on one Filament form gated by one
    // permission. Granting Marketing Update:LoanProduct to edit the former
    // would also grant edit access to the latter. Only SEO/super_admin can
    // reach it until/unless a genuine field-level permission split is built.
    $this->get('/admin/loan-products')->assertForbidden();
});
