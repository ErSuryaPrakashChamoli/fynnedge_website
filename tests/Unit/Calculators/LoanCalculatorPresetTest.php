<?php

use App\Enums\LoanCategory;
use App\Support\Calculators\LoanCalculatorPreset;

it('has a preset for every EMI-style loan category', function (LoanCategory $category) {
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
    LoanCategory::BusinessLoan,
    LoanCategory::LoanAgainstProperty,
]);

it('has no EMI preset for credit cards, which do not repay on a fixed schedule', function () {
    expect(LoanCalculatorPreset::for(LoanCategory::CreditCard))->toBeNull();
});

it('lists exactly the four EMI-style categories as supported', function () {
    expect(LoanCalculatorPreset::supportedCategories())->toBe([
        LoanCategory::PersonalLoan,
        LoanCategory::HomeLoan,
        LoanCategory::LoanAgainstProperty,
        LoanCategory::BusinessLoan,
    ]);
});
