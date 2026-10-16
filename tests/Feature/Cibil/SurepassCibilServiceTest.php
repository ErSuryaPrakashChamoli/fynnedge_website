<?php

use App\Modules\Cibil\DataTransferObjects\CibilSubject;
use App\Modules\Cibil\DataTransferObjects\SurepassCibilResult;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\Gender;
use App\Modules\Cibil\Services\CibilReportDocument;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\Cibil\Services\SurepassCibilService;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Experian\Services\SurepassExperianService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function cibilSubject(Gender $gender = Gender::Female): CibilSubject
{
    return new CibilSubject('Test Fixture', 'AAAPZ9999Z', '9000000001', $gender, CreditConsent::factory()->make());
}

function fetchCibil(string $method = 'fetchReport'): SurepassCibilResult
{
    return app(SurepassCibilService::class)->{$method}(cibilSubject());
}

it('posts the documented body to each CIBIL endpoint with the CIBIL bearer token', function (string $method, string $path) {
    fakeSurepassCibil();

    fetchCibil($method);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://kyc-api.surepass.app/api/v1/credit-report-cibil/'.$path
        && $request->hasHeader('Authorization', 'Bearer test-cibil-token')
        && $request->data() === ['mobile' => '9000000001', 'pan' => 'AAAPZ9999Z', 'name' => 'Test Fixture', 'gender' => 'female', 'consent' => 'Y']);
})->with([
    'score' => ['fetchScore', 'score'],
    'full report' => ['fetchReport', 'fetch-report'],
    'pdf' => ['fetchPdfReport', 'fetch-report-pdf'],
]);

it('accepts each valid response and keeps it whole, with the score and client id', function (string $method, string $fixture, int $score, string $clientId) {
    fakeSurepassCibil();

    $result = fetchCibil($method);
    $document = new CibilReportDocument($result->body ?? []);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->body)->toBe(cibilFixture($fixture))
        ->and($document->score())->toBe($score)
        ->and($document->clientId())->toBe($clientId);
})->with([
    'score' => ['fetchScore', 'score', 742, 'cibil_credit_score_TESTFIXTURE0001'],
    'full report' => ['fetchReport', 'report', 750, 'credit_report_cibil_TESTFIXTURE0001'],
    'pdf with a link (credit_report null)' => ['fetchPdfReport', 'pdf', 750, 'credit_report_cibil_pdf_TESTFIXTURE0001'],
]);

it('accepts a PDF sent inline as Base64 instead of a link', function () {
    $body = cibilFixture('pdf');
    $body['data']['credit_report_link'] = null;
    $body['data']['credit_report_base64'] = base64_encode('%PDF-1.4 inline synthetic PDF');
    fakeSurepassCibil(Http::response($body));

    $result = fetchCibil('fetchPdfReport');

    expect($result->isSuccessful())->toBeTrue()
        ->and((new CibilReportDocument($result->body))->inlinePdf())->toBe('%PDF-1.4 inline synthetic PDF');
});

it('never treats HTTP 200 alone as success, and classifies every failure safely', function (mixed $stub, string $method, CibilErrorCode $code, bool $uncertain) {
    fakeSurepassCibil($stub);

    $result = fetchCibil($method);

    expect($result->errorCode)->toBe($code)
        ->and($result->isUncertain())->toBe($uncertain)
        ->and($result->body)->toBeNull();
})->with([
    'plan lacks the API (401 scope)' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope. Please contact support for help.'], 401), 'fetchScore', CibilErrorCode::ApiNotEnabled, false],
    'invalid credentials (401)' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your token(signature) is invalid.'], 401), 'fetchReport', CibilErrorCode::AuthenticationFailed, false],
    'forbidden (403)' => [fn () => Http::response(['success' => false, 'message' => 'Forbidden'], 403), 'fetchReport', CibilErrorCode::AuthenticationFailed, false],
    'invalid input (400)' => [fn () => Http::response(['errors' => ['gender' => "Gender 'x' is not a valid GenderEnum"], 'message' => 'Input payload validation failed'], 400), 'fetchReport', CibilErrorCode::InvalidRequest, false],
    'rate limited (429)' => [fn () => Http::response(['message' => 'Too many requests'], 429), 'fetchReport', CibilErrorCode::RateLimited, false],
    'balance (402)' => [fn () => Http::response(['success' => false], 402), 'fetchReport', CibilErrorCode::InsufficientBalance, false],
    'no record' => [fn () => Http::response(['success' => false, 'message_code' => 'no_record_found'], 422), 'fetchReport', CibilErrorCode::NoRecordFound, false],
    'provider failure despite 200' => [fn () => Http::response(['success' => false, 'message_code' => 'verification_failed'], 200), 'fetchReport', CibilErrorCode::ProviderRejected, false],
    'server error (500)' => [fn () => Http::response(['message' => 'Internal error'], 500), 'fetchReport', CibilErrorCode::ProviderError, true],
    'malformed JSON' => [fn () => Http::response('<html>', 200), 'fetchReport', CibilErrorCode::MalformedResponse, true],
    'no success flag' => [fn () => Http::response(['data' => ['credit_report' => [['x' => 1]]]], 200), 'fetchReport', CibilErrorCode::MalformedResponse, true],
    'empty credit_report array' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '700', 'credit_report' => []]], 200), 'fetchReport', CibilErrorCode::MissingReportPayload, true],
    'missing credit_score' => [fn () => Http::response(['success' => true, 'data' => ['client_id' => 'x', 'name' => 'x']], 200), 'fetchScore', CibilErrorCode::MissingScore, true],
    'PDF without link or Base64' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '750', 'credit_report' => null, 'credit_report_link' => null, 'credit_report_base64' => null]], 200), 'fetchPdfReport', CibilErrorCode::MissingPdfLink, true],
    'non-https PDF link' => [fn () => Http::response(['success' => true, 'data' => ['credit_report_link' => 'http://files.surepass.test/r.pdf']], 200), 'fetchPdfReport', CibilErrorCode::MissingPdfLink, true],
    'Base64 that is not a PDF' => [fn () => Http::response(['success' => true, 'data' => ['credit_report_base64' => base64_encode('<html>not a pdf</html>')]], 200), 'fetchPdfReport', CibilErrorCode::MissingPdfLink, true],
]);

it('stores a success without a client id, leaving the reference empty rather than inventing one', function () {
    fakeSurepassCibil(Http::response(['success' => true, 'data' => ['credit_score' => '700']]));

    $result = fetchCibil('fetchScore');

    expect($result->isSuccessful())->toBeTrue()
        ->and((new CibilReportDocument($result->body))->clientId())->toBeNull();
});

it('tells a read timeout apart from a connection that never opened', function (string $message, CibilErrorCode $code) {
    fakeSurepassCibil(fn () => throw new ConnectionException($message));

    expect(fetchCibil()->errorCode)->toBe($code);
})->with([
    'read timeout' => ['cURL error 28: Operation timed out after 60001 milliseconds', CibilErrorCode::Timeout],
    'refused' => ['cURL error 7: Failed to connect', CibilErrorCode::ConnectionFailed],
]);

it('sends nothing without its own token, never falling back to the shared or another bureau token', function () {
    fakeSurepassCibil();
    config([
        'services.surepass.cibil_token' => null,
        'services.surepass.token' => 'shared-token',
        'services.surepass.experian_token' => 'experian-token',
    ]);

    expect(CibilSettings::isConfigured())->toBeFalse()
        ->and(CibilSettings::tokenSource())->toBeNull()
        ->and(fetchCibil()->errorCode)->toBe(CibilErrorCode::NotConfigured)
        ->and(app(SurepassCibilService::class)->checkAccess()[SurepassCibilService::REPORT_PATH]['access'])->toBe('not_configured');
    Http::assertNothingSent();
});

it('uses its own base URL and never lends its token to another bureau', function () {
    fakeSurepassCibil();
    Http::fake(['cibil.surepass.test/*' => Http::response(cibilFixture())]);
    config(['services.surepass.cibil_base_url' => 'https://cibil.surepass.test', 'services.surepass.token' => null]);

    expect(fetchCibil()->isSuccessful())->toBeTrue()
        ->and(app(SurepassExperianService::class)->checkAccess()[SurepassExperianService::REPORT_PATH]['access'])->toBe('not_configured');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://cibil.surepass.test/api/v1/credit-report-cibil/fetch-report'
        && $request->hasHeader('Authorization', 'Bearer test-cibil-token'));
});

it('refuses a subject without consent', function () {
    expect(fn () => new CibilSubject('A B', 'AAAPZ9999Z', '9000000001', Gender::Male, CreditConsent::factory()->make(['status' => 'withdrawn'])))
        ->toThrow(InvalidArgumentException::class);
});

it('checks which CIBIL APIs the token may use without sending customer data', function () {
    fakeSurepassCibil(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    expect(app(SurepassCibilService::class)->checkAccess())->toBe([
        SurepassCibilService::REPORT_PATH => ['access' => 'allowed', 'http_status' => 400],
        SurepassCibilService::SCORE_PATH => ['access' => 'not_enabled', 'http_status' => 401],
        SurepassCibilService::PDF_PATH => ['access' => 'not_enabled', 'http_status' => 401],
    ]);
    Http::assertNotSent(fn (Request $request) => $request->data() !== []);
});
