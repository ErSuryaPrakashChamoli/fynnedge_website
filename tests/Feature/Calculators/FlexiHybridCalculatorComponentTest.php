<?php

use App\Enums\LoanCategory;
use App\Models\Lender;
use App\Models\LenderProduct;
use Livewire\Livewire;

it('mounts with the Flexi Hybrid product-level defaults and a computed result using the product-level default initial tenure', function () {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    Livewire::test('flexi-hybrid-calculator')
        ->assertSet('principal', 1000000.0)
        ->assertSet('annualRate', 10.00)
        ->assertSet('totalTenureMonths', 60)
        ->assertSet('lenderProductId', null)
        ->assertSee('Initial EMI')
        ->assertSee('Subsequent EMI')
        ->assertOk();
});

it('lets a visitor switch lenders, applying that lender\'s own initial tenure and rate', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    $lender = Lender::factory()->create(['name' => 'Bajaj Finance']);
    $offer = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $product->id,
        'initial_tenure_months' => 18,
        'interest_rate_from' => 11.25,
    ]);

    Livewire::test('flexi-hybrid-calculator')
        ->assertSet('lenderProductId', $offer->id) // first active lender is auto-selected on mount
        ->call('selectLender', $offer->id)
        ->assertSet('annualRate', 11.25)
        ->assertSee('Initial EMI (18 mo)', false);
});

it('shows an honest fallback instead of a fabricated result when the selected lender has no initial tenure configured', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan, ['default_initial_tenure_months' => null]);
    $lender = Lender::factory()->create(['name' => 'Piramal Finance']);
    $offer = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $product->id,
        'initial_tenure_months' => null,
    ]);

    Livewire::test('flexi-hybrid-calculator')
        ->call('selectLender', $offer->id)
        ->assertSee("hasn't published its initial-tenure terms", false);
});

it('clamps the total tenure to the product\'s configured range and flags the adjustment', function () {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    Livewire::test('flexi-hybrid-calculator')
        ->set('totalTenureMonths', 999)
        ->assertSet('totalTenureMonths', 72)
        ->assertHasErrors('totalTenureMonths');
});
