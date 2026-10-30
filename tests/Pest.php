<?php

use App\Enums\LoanCategory;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Modules\Cibil\DataTransferObjects\CibilCheckRequest;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\Gender as CibilGender;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\CreditBureau\DataTransferObjects\CrifConsentInput;
use App\Modules\CreditBureau\DataTransferObjects\CrifReportRequest;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\DataTransferObjects\EquifaxCheckRequest;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Services\ExperianSettings;
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
 * The Allow/Disallow lines a crawler actually obeys in a robots.txt: those of
 * every group that names it, or of the "*" groups when none does. A crawler
 * ignores every other group (RFC 9309), so finding a line somewhere in the
 * file proves nothing about Googlebot — that is how `Disallow: /language/`
 * once sat under "*" while Googlebot read its own "Allow: /" and crawled on.
 *
 * @return array<int, string> e.g. "Disallow: /admin", in file order
 */
function robotsRulesFor(string $crawler, string $robots): array
{
    $groups = [];
    $readingAgents = false;

    foreach (preg_split('/\R/', $robots) as $line) {
        if (preg_match('/^\s*user-agent\s*:\s*(\S+)/i', $line, $agent)) {
            if (! $readingAgents) {
                $groups[] = ['agents' => [], 'rules' => []];
            }

            $groups[array_key_last($groups)]['agents'][] = strtolower($agent[1]);
            $readingAgents = true;
        } elseif ($groups !== [] && preg_match('/^\s*((?:dis)?allow)\s*:\s*(\S*)/i', $line, $rule)) {
            $groups[array_key_last($groups)]['rules'][] = ucfirst(strtolower($rule[1])).': '.$rule[2];
            $readingAgents = false;
        }
    }

    $naming = fn (string $agent): array => array_filter($groups, fn (array $group): bool => in_array($agent, $group['agents'], true));
    $obeyed = $naming(strtolower($crawler)) ?: $naming('*');

    return array_merge([], ...array_column($obeyed, 'rules'));
}

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

/**
 * Switches CRIF reports on with a fake Surepass token and puts Surepass behind
 * Http::fake(). Stray requests are refused, so no test can ever reach the real
 * — billable — API, even if a fake is mistyped.
 *
 * @param  mixed  $response  Any Http::fake() stub for the CRIF endpoints; defaults to the success fixture.
 */
function fakeSurepassCrif(mixed $response = null): void
{
    config([
        'services.surepass.base_url' => 'https://kyc-api.surepass.app',
        'services.surepass.token' => 'test-surepass-token',
        'services.surepass.timeout' => 60,
        'services.surepass.connect_timeout' => 10,
    ]);
    Setting::set(CrifSettings::ENABLED, true);

    Http::preventStrayRequests();
    Http::fake([
        'kyc-api.surepass.app/api/v1/credit-report-crif/*' => $response ?? Http::response(crifReportFixture()),
    ]);
}

/**
 * The fabricated Surepass CRIF success response (tests/Fixtures/Surepass) — never real customer data.
 *
 * @return array<string, mixed>
 */
function crifReportFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/Surepass/crif-report-success.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A valid employee-initiated CRIF request for the customer, with consent
 * recorded now by phone call. Override any constructor argument by name.
 *
 * @param  array<string, mixed>  $overrides
 */
function crifRequestFor(Customer $customer, array $overrides = []): CrifReportRequest
{
    return new CrifReportRequest(...[
        'customer' => $customer,
        'firstName' => 'Test',
        'lastName' => 'Fixture',
        'mobile' => '9000000001',
        'pan' => 'AAAPZ9999Z',
        'consent' => CrifConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-REF-001', now()->subHour(), true),
        'type' => CrifRequestType::Standard,
        ...$overrides,
    ]);
}

/**
 * Switches Equifax requests on with a fake Surepass token and puts Surepass's
 * credit-report-v2 endpoints behind Http::fake(). Stray requests are refused,
 * so no test can ever reach the real (billable) API.
 *
 * @param  mixed  $response  Any Http::fake() stub for the Equifax endpoints; defaults to the full-report fixture.
 */
function fakeSurepassEquifax(mixed $response = null): void
{
    config([
        'services.surepass.base_url' => 'https://kyc-api.surepass.app',
        'services.surepass.token' => 'test-surepass-token',
        'services.surepass.timeout' => 60,
        'services.surepass.connect_timeout' => 10,
    ]);
    Setting::set(EquifaxSettings::ENABLED, true);

    Http::preventStrayRequests();
    Http::fake([
        'kyc-api.surepass.app/api/v1/credit-report-v2/*' => $response ?? Http::response(equifaxReportFixture()),
    ]);
}

/**
 * The fabricated Surepass Equifax (credit-report-v2) full-report response — never real data.
 *
 * @return array<string, mixed>
 */
function equifaxReportFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/Surepass/equifax-report-success.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A valid Equifax request for the customer, with consent recorded now by phone
 * call. Override any constructor argument by name.
 *
 * @param  array<string, mixed>  $overrides
 */
function equifaxRequestFor(Customer $customer, array $overrides = []): EquifaxCheckRequest
{
    return new EquifaxCheckRequest(...[
        'customer' => $customer,
        'product' => EquifaxProduct::Report,
        'name' => 'Test Fixture',
        'mobile' => '9000000001',
        'pan' => 'AAAPZ9999Z',
        'gender' => Gender::Male,
        'consent' => CreditConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-REF-EQ1', now()->subHour(), true),
        ...$overrides,
    ]);
}

/**
 * Switches Experian requests on with a fake Surepass token and puts Surepass's
 * credit-report-experian endpoints — and the host its PDF links point at —
 * behind Http::fake(). Stray requests are refused, so no test can ever reach
 * the real (billable) API. By default each endpoint answers with its own
 * synthetic fixture (score, fetch-report, fetch-report-pdf).
 *
 * @param  mixed  $response  Any Http::fake() stub for the Experian endpoints.
 */
function fakeSurepassExperian(mixed $response = null): void
{
    config([
        'services.surepass.base_url' => 'https://kyc-api.surepass.app',
        'services.surepass.token' => 'test-surepass-token',
        'services.surepass.timeout' => 60,
        'services.surepass.connect_timeout' => 10,
    ]);
    Setting::set(ExperianSettings::ENABLED, true);

    Http::preventStrayRequests();
    Http::fake([
        'kyc-api.surepass.app/api/v1/credit-report-experian/*' => $response ?? fn (Request $request) => Http::response(experianFixture(match (true) {
            str_ends_with($request->url(), '/score') => 'score',
            str_ends_with($request->url(), '/fetch-report-pdf') => 'pdf',
            default => 'report',
        })),
        'files.surepass.test/*' => Http::response('%PDF-1.4 synthetic test PDF', 200, ['Content-Type' => 'application/pdf']),
    ]);
}

/**
 * A fabricated Surepass Experian response — "score", "report" or "pdf"; never real data.
 *
 * @return array<string, mixed>
 */
function experianFixture(string $product = 'report'): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/Surepass/experian-{$product}-success.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A valid Experian request for the customer, with consent recorded now by
 * phone call. Override any constructor argument by name.
 *
 * @param  array<string, mixed>  $overrides
 */
function experianRequestFor(Customer $customer, array $overrides = []): ExperianCheckRequest
{
    return new ExperianCheckRequest(...[
        'customer' => $customer,
        'product' => ExperianProduct::Report,
        'name' => 'Test Fixture',
        'mobile' => '9000000001',
        'pan' => 'AAAPZ9999Z',
        'consent' => CreditConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-REF-EX1', now()->subHour(), true),
        ...$overrides,
    ]);
}

/**
 * Switches CIBIL requests on with a fake CIBIL-only token (CIBIL never uses
 * the shared one) and puts Surepass's credit-report-cibil endpoints — and the
 * host its PDF links point at — behind Http::fake(). Stray requests are
 * refused, so no test can ever reach the real (billable) API. By default each
 * endpoint answers with its own synthetic fixture (score, fetch-report,
 * fetch-report-pdf).
 *
 * @param  mixed  $response  Any Http::fake() stub for the CIBIL endpoints.
 */
function fakeSurepassCibil(mixed $response = null): void
{
    config([
        'services.surepass.cibil_base_url' => 'https://kyc-api.surepass.app',
        'services.surepass.cibil_token' => 'test-cibil-token',
        'services.surepass.timeout' => 60,
        'services.surepass.connect_timeout' => 10,
    ]);
    Setting::set(CibilSettings::ENABLED, true);

    Http::preventStrayRequests();
    Http::fake([
        'kyc-api.surepass.app/api/v1/credit-report-cibil/*' => $response ?? fn (Request $request) => Http::response(cibilFixture(match (true) {
            str_ends_with($request->url(), '/score') => 'score',
            str_ends_with($request->url(), '/fetch-report-pdf') => 'pdf',
            default => 'report',
        })),
        'files.surepass.test/*' => Http::response('%PDF-1.4 synthetic CIBIL test PDF', 200, ['Content-Type' => 'application/pdf']),
    ]);
}

/**
 * A fabricated Surepass CIBIL response — "score", "report" or "pdf"; never real data.
 *
 * @return array<string, mixed>
 */
function cibilFixture(string $product = 'report'): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/Surepass/cibil-{$product}-success.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A valid CIBIL request for the customer, with consent recorded now by phone
 * call. Override any constructor argument by name.
 *
 * @param  array<string, mixed>  $overrides
 */
function cibilRequestFor(Customer $customer, array $overrides = []): CibilCheckRequest
{
    return new CibilCheckRequest(...[
        'customer' => $customer,
        'product' => CibilProduct::Report,
        'name' => 'Test Fixture',
        'mobile' => '9000000001',
        'pan' => 'AAAPZ9999Z',
        'gender' => CibilGender::Male,
        'consent' => CreditConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-REF-CB1', now()->subHour(), true),
        ...$overrides,
    ]);
}
