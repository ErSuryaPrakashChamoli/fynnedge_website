<?php

use App\Models\LenderProduct;
use App\Modules\Eligibility\Services\FoirCalculator;

beforeEach(function () {
    $this->calculator = new FoirCalculator;
    $this->lenderProduct = new LenderProduct(['interest_rate_from' => 12, 'max_tenure_months' => 60]);
});

it('adds the proposed EMI to existing obligations as a share of income', function () {
    $foir = $this->calculator->calculate(
        loanAmountRequested: 500000,
        totalMonthlyIncome: 70000,
        existingEmiAmount: 5000,
        preferredTenureMonths: 36,
        lenderProduct: $this->lenderProduct,
    );

    expect($foir)->toBeGreaterThan(0)->toBeLessThan(100);
});

it('returns null without a requested loan amount', function () {
    expect($this->calculator->calculate(null, 70000, 0, 36, $this->lenderProduct))->toBeNull();
    expect($this->calculator->calculate(0, 70000, 0, 36, $this->lenderProduct))->toBeNull();
});

it('returns null without income to divide by', function () {
    expect($this->calculator->calculate(500000, null, 0, 36, $this->lenderProduct))->toBeNull();
    expect($this->calculator->calculate(500000, 0, 0, 36, $this->lenderProduct))->toBeNull();
});

it('increases as existing obligations increase', function () {
    $lower = $this->calculator->calculate(500000, 70000, 0, 36, $this->lenderProduct);
    $higher = $this->calculator->calculate(500000, 70000, 20000, 36, $this->lenderProduct);

    expect($higher)->toBeGreaterThan($lower);
});

it('falls back to a default rate and the lender max tenure when unspecified', function () {
    $lenderProduct = new LenderProduct;

    $foir = $this->calculator->calculate(300000, 60000, 0, null, $lenderProduct);

    expect($foir)->not->toBeNull();
});
