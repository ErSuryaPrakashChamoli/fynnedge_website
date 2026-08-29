<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The EMI calculator reads its limits from a published LoanProduct row —
 * there's no hardcoded fallback (that would be exactly the "same limits
 * duplicated in multiple places" the calculator is meant to avoid). Since
 * Feature tests get a fresh, empty database, any test exercising the
 * calculator needs a real row to read from — this seeds one with the same
 * calculator limits production actually ships.
 */
function seedCalculatorProduct(LoanCategory $category, array $overrides = []): LoanProduct
{
    // category must be set via state() *before* withCalculatorLimits(), not just
    // passed to create() — factory state closures only see attributes resolved by
    // prior states in the chain, not the final create() overrides, so
    // withCalculatorLimits() would otherwise read whatever random category the
    // base definition() happened to pick.
    return LoanProduct::factory()
        ->published()
        ->state(['category' => $category])
        ->withCalculatorLimits()
        ->create($overrides);
}
