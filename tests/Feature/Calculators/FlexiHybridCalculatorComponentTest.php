<?php

use App\Enums\LoanCategory;
use App\Models\Lender;
use App\Models\LenderProduct;
use Livewire\Livewire;

it('mounts on an 8-year tenure split into a 2-year initial and 6-year subsequent tenure', function () {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    Livewire::test('flexi-hybrid-calculator')
        ->assertSet('principal', 1000000.0)
        ->assertSet('annualRate', 10.00)
        ->assertSet('totalTenureMonths', 96)
        ->assertSet('lenderProductId', null)
        ->assertSee('Initial EMI (24 mo)', false)
        ->assertSee('Subsequent EMI (72 mo)', false)
        ->assertOk();
});

it('uses a 3-year initial tenure on a 9-year loan', function () {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    $component = Livewire::test('flexi-hybrid-calculator')
        ->set('totalTenureMonths', 108)
        ->assertHasNoErrors()
        ->assertSee('Initial EMI (36 mo)', false)
        ->assertSee('Subsequent EMI (72 mo)', false);

    expect($component->instance()->result())->toMatchArray([
        'initial_tenure_months' => 36,
        'subsequent_tenure_months' => 72,
        'total_tenure_months' => 108,
    ]);
});

it('lets a visitor switch lenders, applying that lender\'s rate with the tenure-based initial tenure', function () {
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
        ->assertSee('Initial EMI (24 mo)', false);
});

it('resets an emptied field to the product minimum instead of crashing', function (string $property, string $presetKey) {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    $component = Livewire::test('flexi-hybrid-calculator');
    $minimum = $component->instance()->preset()[$presetKey];

    $component->set($property, '')
        ->assertOk()
        ->assertSet($property, $minimum)
        ->assertHasErrors([$property]);
})->with([
    'loan amount' => ['principal', 'min_amount'],
    'interest rate' => ['annualRate', 'min_rate'],
]);

it('snaps a total tenure outside 8 or 9 years to the nearest allowed tenure and flags the adjustment', function (mixed $tenure, int $expected) {
    seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    Livewire::test('flexi-hybrid-calculator')
        ->set('totalTenureMonths', $tenure)
        ->assertOk()
        ->assertSet('totalTenureMonths', $expected)
        ->assertHasErrors('totalTenureMonths');
})->with([
    'emptied' => ['', 96],
    'too short' => [60, 96],
    'too long' => [999, 108],
    'between the two' => [100, 96],
]);
