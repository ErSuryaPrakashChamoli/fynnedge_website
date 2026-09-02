<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use App\Models\MarketingSection;

it('shows the Flexi Hybrid ticker strip immediately below the header on the homepage', function () {
    LoanProduct::factory()->published()->create(['category' => LoanCategory::FlexiHybridTermLoan]);

    $response = $this->get('/')->assertOk();

    $response->assertSeeInOrder([
        'id="main-content"',
        'flexi-hybrid-panel',
        'Flexi Hybrid Term Loan',
        '(Overdraft)',
        'Bajaj Finance',
        'Tata Capital',
        'Piramal Finance',
        'Kotak Mahindra Bank',
    ], false);
});

it('scrolls the ticker text right to left using the existing marquee animation', function () {
    LoanProduct::factory()->published()->create(['category' => LoanCategory::FlexiHybridTermLoan]);

    $this->get('/')->assertOk()->assertSee('animate-marquee', false);
});

it('links Apply Now on the ticker straight to the Flexi Hybrid product\'s application flow', function () {
    $product = LoanProduct::factory()->published()->create(['category' => LoanCategory::FlexiHybridTermLoan]);

    $this->get('/')->assertOk()->assertSee(route('loans.apply', $product), false);
});

it('does not render the ticker at all when no Flexi Hybrid product is published', function () {
    $response = $this->get('/')->assertOk();

    $response->assertDontSee('flexi-hybrid-panel', false);
});

it('shows admin-edited badge, marquee text and button copy when a home_flexi_hybrid_ticker MarketingSection is published', function () {
    LoanProduct::factory()->published()->create(['category' => LoanCategory::FlexiHybridTermLoan]);
    MarketingSection::factory()->published()->create([
        'placement' => 'home_flexi_hybrid_ticker',
        'heading' => 'Editors Pick',
        'description' => 'Custom marquee copy set from the admin panel',
        'cta_label' => 'See details',
        'cta_url' => '/loans/flexi-hybrid-term-loan',
    ]);

    $response = $this->get('/')->assertOk();

    $response->assertSee('Editors Pick', false);
    $response->assertSee('Custom marquee copy set from the admin panel', false);
    $response->assertSee('See details', false);
    $response->assertSee('/loans/flexi-hybrid-term-loan', false);
    $response->assertDontSee('Bajaj Finance', false);
});

it('keeps the hardcoded ticker defaults when the home_flexi_hybrid_ticker MarketingSection is only a draft', function () {
    $product = LoanProduct::factory()->published()->create(['category' => LoanCategory::FlexiHybridTermLoan]);
    MarketingSection::factory()->create([
        'placement' => 'home_flexi_hybrid_ticker',
        'heading' => 'Draft heading',
        'description' => 'Draft marquee copy',
    ]);

    $response = $this->get('/')->assertOk();

    $response->assertSee('Bajaj Finance', false);
    $response->assertSee(route('loans.apply', $product), false);
    $response->assertDontSee('Draft marquee copy', false);
});
