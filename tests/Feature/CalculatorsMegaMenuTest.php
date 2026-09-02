<?php

use App\Enums\LoanCategory;
use App\Support\Calculators\CalculatorCatalog;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Every category linked from the EMI/prepayment columns of the mega menu needs a
    // published, calculator-configured LoanProduct behind it, or its route 404s.
    foreach ([
        LoanCategory::PersonalLoan,
        LoanCategory::HomeLoan,
        LoanCategory::BusinessLoan,
        LoanCategory::GoldLoan,
        LoanCategory::TwoWheelerLoan,
        LoanCategory::LoanAgainstProperty,
        LoanCategory::TermLoan,
        LoanCategory::TractorLoan,
        LoanCategory::MudraLoan,
        LoanCategory::FlexiHybridTermLoan,
    ] as $category) {
        seedCalculatorProduct($category);
    }
});

it('shows every calculator label in the header mega menu', function () {
    $response = $this->get('/')->assertOk();

    foreach (CalculatorCatalog::groups() as $groupLabel => $items) {
        $response->assertSee($groupLabel);

        foreach ($items as $item) {
            $response->assertSee($item['label']);
        }
    }
});

it('resolves every calculator link in the catalog to a real, working route', function () {
    foreach (CalculatorCatalog::groups() as $items) {
        foreach ($items as $item) {
            expect(Route::has($item['route']))->toBeTrue("Route [{$item['route']}] does not exist.");

            $status = $this->get(route($item['route'], $item['params']))->getStatusCode();

            expect($status)->toBe(200, "Route [{$item['route']}] with params ".json_encode($item['params'])." returned {$status}.");
        }
    }
});
