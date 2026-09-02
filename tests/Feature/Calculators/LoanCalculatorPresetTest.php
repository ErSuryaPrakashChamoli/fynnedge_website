<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use App\Support\Calculators\LoanCalculatorPreset;

it('reads a preset from the published LoanProduct row for every EMI-style loan category', function (LoanCategory $category) {
    seedCalculatorProduct($category);

    $preset = LoanCalculatorPreset::for($category);

    expect($preset)->not->toBeNull();
    expect($preset['min_amount'])->toBeLessThan($preset['max_amount']);
    expect($preset['default_amount'])->toBeGreaterThanOrEqual($preset['min_amount'])->toBeLessThanOrEqual($preset['max_amount']);
    expect($preset['min_rate'])->toBeLessThan($preset['max_rate']);
    expect($preset['default_rate'])->toBeGreaterThanOrEqual($preset['min_rate'])->toBeLessThanOrEqual($preset['max_rate']);
    expect($preset['min_years'])->toBeLessThan($preset['max_years']);
    expect($preset['default_years'])->toBeGreaterThanOrEqual($preset['min_years'])->toBeLessThanOrEqual($preset['max_years']);
})->with([
    LoanCategory::PersonalLoan,
    LoanCategory::HomeLoan,
    LoanCategory::CarLoan,
    LoanCategory::BusinessLoan,
    LoanCategory::LoanAgainstProperty,
    LoanCategory::GoldLoan,
    LoanCategory::TwoWheelerLoan,
    LoanCategory::TermLoan,
    LoanCategory::TractorLoan,
    LoanCategory::MudraLoan,
]);

it('has no EMI preset for credit cards, which do not repay on a fixed schedule', function () {
    seedCalculatorProduct(LoanCategory::CreditCard);

    expect(LoanCalculatorPreset::for(LoanCategory::CreditCard))->toBeNull();
});

it('has no preset when no published LoanProduct exists for a category yet — no hardcoded fallback', function () {
    expect(LoanCalculatorPreset::for(LoanCategory::PersonalLoan))->toBeNull();
});

it('has no preset for an unpublished (draft) LoanProduct, even with calculator limits set', function () {
    LoanProduct::factory()->withCalculatorLimits()->create(['category' => LoanCategory::PersonalLoan]);

    expect(LoanCalculatorPreset::for(LoanCategory::PersonalLoan))->toBeNull();
});

it('has no preset when the LoanProduct exists but calculator limits were never filled in', function () {
    LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan]);

    expect(LoanCalculatorPreset::for(LoanCategory::PersonalLoan))->toBeNull();
});

it('lists exactly the five EMI-style categories that have a complete, published configuration', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    seedCalculatorProduct(LoanCategory::HomeLoan);
    seedCalculatorProduct(LoanCategory::CarLoan);
    seedCalculatorProduct(LoanCategory::LoanAgainstProperty);
    seedCalculatorProduct(LoanCategory::BusinessLoan);
    seedCalculatorProduct(LoanCategory::CreditCard);

    expect(LoanCalculatorPreset::supportedCategories())->toBe([
        LoanCategory::PersonalLoan,
        LoanCategory::HomeLoan,
        LoanCategory::CarLoan,
        LoanCategory::LoanAgainstProperty,
        LoanCategory::BusinessLoan,
    ]);
});

it('exposes the exact limits stored on the LoanProduct row, not different numbers', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan, [
        'min_amount' => 25_000,
        'max_amount' => 5_000_000,
        'default_amount' => 500_000,
        'min_tenure_months' => 12,
        'max_tenure_months' => 84,
        'default_tenure_months' => 36,
        'min_interest_rate' => 10.49,
        'max_interest_rate' => 30.00,
        'default_interest_rate' => 13.50,
        'interest_rate_note' => '10.49% – 24%+',
    ]);

    $preset = LoanCalculatorPreset::for(LoanCategory::PersonalLoan);

    expect($preset['max_amount'])->toBe(5_000_000.0);
    expect($preset['max_years'])->toBe(7);
    expect($preset['max_rate'])->toBe(30.0);
    expect($preset['rate_note'])->toBe('10.49% – 24%+');
});
