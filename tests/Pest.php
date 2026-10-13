<?php

use App\Enums\LoanCategory;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

/**
 * Public pages only show a rate that is published, verified and in date
 * (App\Support\Rates\RateBook) — the old interest_rate_from/to columns on a
 * lender offer are never displayed. A test that needs a lender's rate on a
 * page publishes one through this helper.
 */
function publishRate(LenderProduct $offer, float $rateFrom, ?float $rateTo = null, array $overrides = []): LenderProductRate
{
    return LenderProductRate::factory()
        ->published()
        ->for($offer, 'lenderProduct')
        ->create([
            'rate_min' => $rateFrom,
            'rate_max' => $rateTo,
            'rate_unit' => $offer->loanProduct->category->rateUnit(),
            ...$overrides,
        ]);
}

/**
 * Puts the suite on the real Areach provider behind Http::fake(), so a flow
 * test runs the actual send path end to end. Stray requests are refused, so a
 * test can never reach the real gateway even if the fake is mistyped.
 *
 * @param  mixed  $response  Any Http::fake() stub; defaults to an explicit success.
 */
function fakeAreachSms(mixed $response = null): void
{
    config([
        'mobile_otp.sms_provider' => 'areach',
        'services.areach.base_url' => 'https://api.areach.in/v1',
        'services.areach.key' => 'test-areach-key',
        'services.areach.sender' => 'FYNNED',
        'services.areach.timeout' => 10,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.areach.in/*' => $response ?? Http::response(['status' => 'success', 'message_id' => 'areach-msg-1']),
    ]);
}

/**
 * The code from the most recent faked Areach SMS — the only place a code ever
 * appears, since no response, session or log line carries it.
 */
function lastTextedOtp(): ?string
{
    $request = collect(Http::recorded())
        ->map(fn (array $pair): Request => $pair[0])
        ->last(fn (Request $request): bool => str_contains($request->url(), 'api.areach.in'));

    $length = MobileOtpChallenge::codeLength();

    return $request && preg_match('/\b(\d{'.$length.'})\b/', (string) $request['text'], $match) ? $match[1] : null;
}
