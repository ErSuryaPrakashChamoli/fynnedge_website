<?php

use App\Enums\LoanCategory;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use Livewire\Livewire;

function flexiHybridOffer(LoanProduct $product, string $lenderName, int $minTenure, int $maxTenure, ?int $initialTenure, ?float $rate = 11.0): LenderProduct
{
    return LenderProduct::factory()->create([
        'lender_id' => Lender::factory()->create(['name' => $lenderName])->id,
        'loan_product_id' => $product->id,
        'min_amount' => null,
        'max_amount' => null,
        'min_tenure_months' => $minTenure,
        'max_tenure_months' => $maxTenure,
        'initial_tenure_months' => $initialTenure,
        'interest_rate_from' => $rate,
    ]);
}

it('mounts on the first lender\'s structure closest to the product\'s default tenure', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    $bajaj = flexiHybridOffer($product, 'Bajaj Finance', 96, 108, 24, 10.5);

    $component = Livewire::test('flexi-hybrid-calculator')
        ->assertSet('principal', 1000000.0)
        ->assertSet('lenderProductId', $bajaj->id)
        ->assertSet('totalTenureMonths', 96)
        ->assertOk();

    expect($component->instance()->result())->toMatchArray(['initial_tenure_months' => 24, 'subsequent_tenure_months' => 72]);
});

it('offers Bajaj both 2 + 6 and 3 + 6, using a 3-year initial tenure on 9 years', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    flexiHybridOffer($product, 'Bajaj Finance', 96, 108, 24);

    $component = Livewire::test('flexi-hybrid-calculator')
        ->assertSee('2 + 6 yrs')
        ->assertSee('3 + 6 yrs')
        ->set('totalTenureMonths', 108)
        ->assertHasNoErrors();

    expect($component->instance()->result())->toMatchArray(['initial_tenure_months' => 36, 'subsequent_tenure_months' => 72]);
});

it('switches to the selected lender\'s own structure and rate', function (string $lenderName, int $min, int $max, int $initial, int $subsequent) {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    flexiHybridOffer($product, 'Bajaj Finance', 96, 108, 24, 10.5);
    $offer = flexiHybridOffer($product, $lenderName, $min, $max, $initial, 11.25);

    $component = Livewire::test('flexi-hybrid-calculator')
        ->call('selectLender', $offer->id)
        ->assertSet('annualRate', 11.25)
        ->assertSet('totalTenureMonths', $min)
        ->assertHasNoErrors();

    expect($component->instance()->result())->toMatchArray(['initial_tenure_months' => $initial, 'subsequent_tenure_months' => $subsequent]);
})->with([
    'Kotak 1 + 5' => ['Kotak Mahindra Bank', 72, 72, 12, 60],
    'Piramal 2 + 5' => ['Piramal Finance', 84, 84, 24, 60],
    'Tata 2 + 5' => ['Tata Capital', 84, 84, 24, 60],
]);

it('offers every lender\'s structure in the generic estimate and compares each lender on its own structure', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    flexiHybridOffer($product, 'Bajaj Finance', 96, 108, 24);
    flexiHybridOffer($product, 'Kotak Mahindra Bank', 72, 72, 12);
    flexiHybridOffer($product, 'Piramal Finance', 84, 84, 24);

    $component = Livewire::test('flexi-hybrid-calculator')->call('selectLender', null);

    expect(array_column($component->instance()->tenureOptions(), 'total'))->toBe([72, 84, 96, 108]);

    $component->set('totalTenureMonths', 72);
    $structures = collect($component->instance()->lenderComparison())
        ->mapWithKeys(fn (array $row) => [$row['offer']->lender->name => $row['result']['initial_tenure_months'].'+'.$row['result']['subsequent_tenure_months']]);

    expect($structures->all())->toBe([
        'Bajaj Finance' => '24+72',
        'Kotak Mahindra Bank' => '12+60',
        'Piramal Finance' => '24+60',
    ]);
});

it('shows an honest fallback instead of a fabricated result when the selected lender has no tenure structure', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan, ['default_initial_tenure_months' => null]);
    $offer = flexiHybridOffer($product, 'Poonawalla Fincorp', 12, 60, null);

    Livewire::test('flexi-hybrid-calculator')
        ->call('selectLender', $offer->id)
        ->assertSee("hasn't published its tenure structure", false);
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

it('snaps a tenure the lender does not offer to its closest structure and flags the adjustment', function (mixed $tenure, int $expected) {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    flexiHybridOffer($product, 'Bajaj Finance', 96, 108, 24);

    Livewire::test('flexi-hybrid-calculator')
        ->set('totalTenureMonths', $tenure)
        ->assertOk()
        ->assertSet('totalTenureMonths', $expected)
        ->assertHasErrors('totalTenureMonths');
})->with([
    'emptied' => ['', 96],
    'too short' => [60, 96],
    'too long' => [999, 108],
]);
