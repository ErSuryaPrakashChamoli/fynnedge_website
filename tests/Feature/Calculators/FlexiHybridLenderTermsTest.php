<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Support\Calculators\FlexiHybridLenderTerms;

it('offers exactly Bajaj, Tata Capital, Aditya Birla and Piramal, replacing any other lender', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    $poonawalla = LenderProduct::factory()->create([
        'lender_id' => Lender::factory()->create(['name' => 'Poonawalla Fincorp', 'slug' => 'poonawalla-fincorp'])->id,
        'loan_product_id' => $product->id,
        'interest_rate_from' => null,
    ]);

    FlexiHybridLenderTerms::sync($product);

    expect($poonawalla->fresh()->status)->toBe(LenderStatus::Inactive);

    $active = $product->lenderProducts()->where('status', LenderStatus::Active)->with('lender')->get();

    expect($active->pluck('lender.name')->sort()->values()->all())
        ->toBe(['Aditya Birla Finance', 'Bajaj Finance', 'Piramal Finance', 'Tata Capital']);

    $active->each(function (LenderProduct $offer): void {
        expect($offer->interest_rate_from)->not->toBeNull()
            ->and($offer->hybridTenureOptions())->not->toBeEmpty();
    });
});

it('stores each lender\'s published initial + subsequent structures', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);

    FlexiHybridLenderTerms::sync($product);

    $structuresFor = fn (string $slug): array => collect(
        $product->lenderProducts()->whereRelation('lender', 'slug', $slug)->first()->hybridTenureOptions()
    )->map(fn (array $option): string => $option['initial'].'+'.$option['subsequent'])->all();

    expect($structuresFor('tata-capital'))->toBe(['12+48', '12+60', '24+60', '24+72'])
        ->and($structuresFor('piramal-finance'))->toBe(['24+60'])
        ->and($structuresFor('bajaj-finance'))->toBe(['12+48', '24+72', '36+72'])
        ->and($structuresFor('aditya-birla-finance'))->toBe(['12+60', '24+48', '12+72', '24+60', '12+84', '24+72']);
});

it('compares all four lenders with real figures on the calculator page, opening on Bajaj', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    FlexiHybridLenderTerms::sync($product);

    $this->get(route('calculators.emi', 'flexi-hybrid-term-loan'))
        ->assertOk()
        ->assertSee(['Compare all lenders', 'Bajaj Finance', 'Tata Capital', 'Aditya Birla Finance', 'Piramal Finance', '12.99%', '2 + 6 yrs', '2 + 5 yrs'])
        ->assertSeeInOrder(['Generic estimate', 'Bajaj Finance', 'role="radiogroup"'], false)
        ->assertDontSee('Poonawalla Fincorp')
        ->assertDontSee('Available on request');
});
