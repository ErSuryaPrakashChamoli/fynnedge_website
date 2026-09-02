<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Models\NavigationLink;

/**
 * Phase 5.2 audit outcome: the header's loan mega-menu, calculator mega-menu,
 * and credit-score dropdown, plus the footer's Company/Legal columns, stay
 * code-driven — NOT handed to NavigationLink. They're either safety-critical
 * (LoanMegaMenu::categories() only lists a category with a real, published
 * LoanProduct + working JourneyDefinition, preventing dead-end "Apply" links)
 * or structurally tied 1:1 to real controller/route implementations
 * (CalculatorCatalog, credit-score bureaus) that a generic link editor could
 * not safely reproduce — an admin "adding" a calculator link doesn't create
 * the calculator behind it. NavigationLink remains purely additive (footer
 * "Quick Links" only). These tests lock that decision in as regression
 * protection, not just documentation.
 */
it('keeps the header loan mega-menu code-driven and unaffected by NavigationLink records', function () {
    $product = LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan]);
    NavigationLink::factory()->create(['label' => 'Some Quick Link', 'route_name' => 'contact']);

    $response = $this->get('/');

    $response->assertOk()->assertSee($product->name);
});

it('keeps the calculator mega-menu entries intact regardless of navigation link configuration', function () {
    NavigationLink::factory()->create(['label' => 'Some Quick Link', 'route_name' => 'contact']);

    $this->get('/calculators')
        ->assertOk()
        ->assertSee('Fixed Deposit Calculator')
        ->assertSee('GST Calculator')
        ->assertSee('SIP Calculator');
});

it('keeps the header credit-score bureau links intact', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('credit-score.show', ['bureau' => 'cibil']), false)
        ->assertSee(route('credit-score.show', ['bureau' => 'experian']), false)
        ->assertSee(route('credit-score.show', ['bureau' => 'equifax']), false)
        ->assertSee(route('credit-score.show', ['bureau' => 'crif']), false);
});

it('keeps the footer Company and Legal columns hardcoded and always present', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('About')
        ->assertSee('Careers')
        ->assertSee('Grievance')
        ->assertSee('Privacy Policy')
        ->assertSee('Terms')
        ->assertSee('Credit Report Terms of Use');
});

it('excludes an unpublished loan category from the mega-menu, same as before this phase', function () {
    LoanProduct::factory()->create([
        'category' => LoanCategory::HomeLoan,
        'status' => PublishStatus::Draft,
    ]);

    $this->get('/')->assertOk()->assertDontSee('/loans/apply');
});
