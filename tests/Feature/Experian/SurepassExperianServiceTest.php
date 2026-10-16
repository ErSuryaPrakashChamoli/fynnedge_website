<?php

use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\SurepassCrifService;
use App\Modules\Equifax\Services\SurepassEquifaxService;
use App\Modules\Experian\DataTransferObjects\ExperianSubject;
use App\Modules\Experian\DataTransferObjects\SurepassExperianResult;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Services\ExperianReportDocument;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\Experian\Services\SurepassExperianService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function experianSubject(): ExperianSubject
{
    return new ExperianSubject('Test Fixture', 'AAAPZ9999Z', '9000000001', CreditConsent::factory()->make());
}

function fetchExperian(string $method = 'fetchReport'): SurepassExperianResult
{
    return app(SurepassExperianService::class)->{$method}(experianSubject());
}

it('posts the documented body to each Experian endpoint with the bearer token', function (string $method, string $path) {
    fakeSurepassExperian();

    fetchExperian($method);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://kyc-api.surepass.app/api/v1/credit-report-experian/'.$path
        && $request->hasHeader('Authorization', 'Bearer test-surepass-token')
        && $request->data() === ['name' => 'Test Fixture', 'pan' => 'AAAPZ9999Z', 'mobile' => '9000000001', 'consent' => 'Y']);
})->with([
    'score' => ['fetchScore', 'score'],
    'full report' => ['fetchReport', 'fetch-report'],
    'pdf' => ['fetchPdfReport', 'fetch-report-pdf'],
]);

it('accepts each valid response and keeps it whole, with the score and client id', function (string $method, string $fixture, int $score, string $clientId) {
    fakeSurepassExperian();

    $result = fetchExperian($method);
    $document = new ExperianReportDocument($result->body ?? []);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->body)->toBe(experianFixture($fixture))
        ->and($document->score())->toBe($score)
        ->and($document->clientId())->toBe($clientId);
})->with([
    'score' => ['fetchScore', 'score', 796, 'experian_credit_score_TESTFIXTURE0001'],
    'full report' => ['fetchReport', 'report', 792, 'credit_report_experian_TESTFIXTURE0001'],
    'pdf (empty credit_report, link present)' => ['fetchPdfReport', 'pdf', 761, 'credit_report_experian_pdf_TESTFIXTURE0001'],
]);

it('never treats HTTP 200 alone as success, and classifies every failure safely', function (mixed $stub, string $method, ExperianErrorCode $code, bool $uncertain) {
    fakeSurepassExperian($stub);

    $result = fetchExperian($method);

    expect($result->errorCode)->toBe($code)
        ->and($result->isUncertain())->toBe($uncertain)
        ->and($result->body)->toBeNull();
})->with([
    'plan lacks the API (401 scope)' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope. Please contact support for help.'], 401), 'fetchScore', ExperianErrorCode::ApiNotEnabled, false],
    'invalid credentials (401)' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your token(signature) is invalid.'], 401), 'fetchReport', ExperianErrorCode::AuthenticationFailed, false],
    'forbidden (403)' => [fn () => Http::response(['success' => false, 'message' => 'Forbidden'], 403), 'fetchReport', ExperianErrorCode::AuthenticationFailed, false],
    'invalid input (400)' => [fn () => Http::response(['errors' => ['pan' => 'PAN Number Min string length is 10'], 'message' => 'Input payload validation failed'], 400), 'fetchReport', ExperianErrorCode::InvalidRequest, false],
    'rate limited (429)' => [fn () => Http::response(['message' => 'Too many requests'], 429), 'fetchReport', ExperianErrorCode::RateLimited, false],
    'balance (402)' => [fn () => Http::response(['success' => false], 402), 'fetchReport', ExperianErrorCode::InsufficientBalance, false],
    'no record' => [fn () => Http::response(['success' => false, 'message_code' => 'no_record_found'], 422), 'fetchReport', ExperianErrorCode::NoRecordFound, false],
    'provider failure despite 200' => [fn () => Http::response(['success' => false, 'message_code' => 'verification_failed'], 200), 'fetchReport', ExperianErrorCode::ProviderRejected, false],
    'server error (500)' => [fn () => Http::response(['message' => 'Internal error'], 500), 'fetchReport', ExperianErrorCode::ProviderError, true],
    'malformed JSON' => [fn () => Http::response('<html>', 200), 'fetchReport', ExperianErrorCode::MalformedResponse, true],
    'no success flag' => [fn () => Http::response(['data' => ['credit_report' => ['x' => 1]]], 200), 'fetchReport', ExperianErrorCode::MalformedResponse, true],
    'missing credit_report' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '700', 'credit_report' => []]], 200), 'fetchReport', ExperianErrorCode::MissingReportPayload, true],
    'missing credit_score' => [fn () => Http::response(['success' => true, 'data' => ['client_id' => 'x', 'name' => 'x']], 200), 'fetchScore', ExperianErrorCode::MissingScore, true],
    'missing credit_report_link' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '761', 'credit_report' => []]], 200), 'fetchPdfReport', ExperianErrorCode::MissingPdfLink, true],
    'non-https PDF link' => [fn () => Http::response(['success' => true, 'data' => ['credit_report_link' => 'http://files.surepass.test/r.pdf']], 200), 'fetchPdfReport', ExperianErrorCode::MissingPdfLink, true],
    'not a URL' => [fn () => Http::response(['success' => true, 'data' => ['credit_report_link' => 'https://']], 200), 'fetchPdfReport', ExperianErrorCode::MissingPdfLink, true],
]);

it('stores a success without a client id, leaving the reference empty rather than inventing one', function () {
    fakeSurepassExperian(Http::response(['success' => true, 'data' => ['credit_score' => '700']]));

    $result = fetchExperian('fetchScore');

    expect($result->isSuccessful())->toBeTrue()
        ->and((new ExperianReportDocument($result->body))->clientId())->toBeNull();
});

it('tells a read timeout apart from a connection that never opened', function (string $message, ExperianErrorCode $code) {
    fakeSurepassExperian(fn () => throw new ConnectionException($message));

    expect(fetchExperian()->errorCode)->toBe($code);
})->with([
    'read timeout' => ['cURL error 28: Operation timed out after 60001 milliseconds', ExperianErrorCode::Timeout],
    'refused' => ['cURL error 7: Failed to connect', ExperianErrorCode::ConnectionFailed],
]);

it('sends nothing without a token and refuses a subject without consent', function () {
    fakeSurepassExperian();
    config(['services.surepass.token' => null]);

    expect(fetchExperian()->errorCode)->toBe(ExperianErrorCode::NotConfigured);
    Http::assertNothingSent();

    expect(fn () => new ExperianSubject('A B', 'AAAPZ9999Z', '9000000001', CreditConsent::factory()->make(['status' => 'withdrawn'])))
        ->toThrow(InvalidArgumentException::class);
});

it('uses the Experian-only token and base URL when set, without lending them to CRIF or Equifax', function () {
    fakeSurepassExperian();
    Http::fake(['experian.surepass.test/*' => Http::response(experianFixture())]);
    config([
        'services.surepass.token' => null,
        'services.surepass.experian_token' => 'experian-only-token',
        'services.surepass.experian_base_url' => 'https://experian.surepass.test',
    ]);

    expect(fetchExperian()->isSuccessful())->toBeTrue()
        ->and(ExperianSettings::isConfigured())->toBeTrue()
        ->and(ExperianSettings::tokenSource())->toBe('SUREPASS_EXPERIAN_TOKEN')
        ->and(CrifSettings::isConfigured())->toBeFalse()
        ->and(app(SurepassCrifService::class)->checkAccess()[SurepassCrifService::REPORT_PATH]['access'])->toBe('not_configured')
        ->and(app(SurepassEquifaxService::class)->checkAccess()[SurepassEquifaxService::REPORT_PATH]['access'])->toBe('not_configured');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://experian.surepass.test/api/v1/credit-report-experian/fetch-report'
        && $request->hasHeader('Authorization', 'Bearer experian-only-token'));
});

it('checks which Experian APIs the token may use without sending customer data', function () {
    fakeSurepassExperian(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    expect(app(SurepassExperianService::class)->checkAccess())->toBe([
        SurepassExperianService::REPORT_PATH => ['access' => 'allowed', 'http_status' => 400],
        SurepassExperianService::SCORE_PATH => ['access' => 'not_enabled', 'http_status' => 401],
        SurepassExperianService::PDF_PATH => ['access' => 'not_enabled', 'http_status' => 401],
    ]);
    Http::assertNotSent(fn (Request $request) => $request->data() !== []);
});
