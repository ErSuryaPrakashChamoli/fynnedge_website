<?php

use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Equifax\DataTransferObjects\EquifaxSubject;
use App\Modules\Equifax\DataTransferObjects\SurepassEquifaxResult;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Services\SurepassEquifaxService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function equifaxSubject(): EquifaxSubject
{
    return new EquifaxSubject('Test Fixture', 'AAAPZ9999Z', '9000000001', Gender::Female, CreditConsent::factory()->make());
}

function fetchEquifaxReport(): SurepassEquifaxResult
{
    return app(SurepassEquifaxService::class)->fetchReport(equifaxSubject());
}

it('posts the documented body to each Equifax endpoint with the bearer token', function (string $method, string $path, array $extra) {
    fakeSurepassEquifax(Http::response(['success' => true, 'data' => ['credit_score' => '700', 'credit_report' => ['x' => 1], 'credit_report_link' => 'https://files.example.test/r.pdf']]));

    app(SurepassEquifaxService::class)->{$method}(equifaxSubject());

    Http::assertSent(fn (Request $request) => $request->url() === 'https://kyc-api.surepass.app/api/v1/credit-report-v2/'.$path
        && $request->hasHeader('Authorization', 'Bearer test-surepass-token')
        && $request->data() === [
            'name' => 'Test Fixture',
            'id_number' => 'AAAPZ9999Z',
            'id_type' => 'pan',
            'mobile' => '9000000001',
            'consent' => 'Y',
            'gender' => 'female',
            ...$extra,
        ]);
})->with([
    'score' => ['fetchScore', 'score', ['report_type' => 'json']],
    'full report' => ['fetchReport', 'fetch-report', []],
    'pdf' => ['fetchPdfReport', 'fetch-pdf-report', []],
]);

it('keeps the complete nested response for a successful report', function () {
    fakeSurepassEquifax();

    $result = fetchEquifaxReport();

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->body)->toBe(equifaxReportFixture());
});

it('never treats HTTP 200 alone as success', function (mixed $stub, string $method, EquifaxErrorCode $code, bool $uncertain) {
    fakeSurepassEquifax($stub);

    $result = app(SurepassEquifaxService::class)->{$method}(equifaxSubject());

    expect($result->errorCode)->toBe($code)
        ->and($result->isUncertain())->toBe($uncertain)
        ->and($result->body)->toBeNull();
})->with([
    'plan lacks the API' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope. Please contact support for help.'], 401), 'fetchScore', EquifaxErrorCode::ApiNotEnabled, false],
    'bad token' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your token(signature) is invalid.'], 401), 'fetchReport', EquifaxErrorCode::AuthenticationFailed, false],
    'invalid input (400)' => [fn () => Http::response(['errors' => ['gender' => 'invalid'], 'message' => 'Input payload validation failed'], 400), 'fetchReport', EquifaxErrorCode::InvalidRequest, false],
    'no record' => [fn () => Http::response(['success' => false, 'message_code' => 'no_record_found'], 422), 'fetchReport', EquifaxErrorCode::NoRecordFound, false],
    'balance' => [fn () => Http::response(['success' => false], 402), 'fetchReport', EquifaxErrorCode::InsufficientBalance, false],
    'provider failure despite 200' => [fn () => Http::response(['success' => false, 'message_code' => 'verification_failed'], 200), 'fetchReport', EquifaxErrorCode::ProviderRejected, false],
    'gateway error' => [fn () => Http::response('', 502), 'fetchReport', EquifaxErrorCode::ProviderError, true],
    'malformed JSON' => [fn () => Http::response('<html>', 200), 'fetchReport', EquifaxErrorCode::MalformedResponse, true],
    'no success flag' => [fn () => Http::response(['data' => ['credit_report' => ['x' => 1]]], 200), 'fetchReport', EquifaxErrorCode::MalformedResponse, true],
    'success without report' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '700']], 200), 'fetchReport', EquifaxErrorCode::MissingReportPayload, true],
    'success without score' => [fn () => Http::response(['success' => true, 'data' => ['name' => 'x']], 200), 'fetchScore', EquifaxErrorCode::MissingScore, true],
    'pdf success without link' => [fn () => Http::response(['success' => true, 'data' => ['credit_report_link' => '']], 200), 'fetchPdfReport', EquifaxErrorCode::MissingPdfLink, false],
]);

it('tells a read timeout apart from a connection that never opened', function (string $message, EquifaxErrorCode $code) {
    fakeSurepassEquifax(fn () => throw new ConnectionException($message));

    expect(fetchEquifaxReport()->errorCode)->toBe($code);
})->with([
    'read timeout' => ['cURL error 28: Operation timed out after 60001 milliseconds', EquifaxErrorCode::Timeout],
    'refused' => ['cURL error 7: Failed to connect', EquifaxErrorCode::ConnectionFailed],
]);

it('sends nothing without a token and refuses a subject without consent', function () {
    fakeSurepassEquifax();
    config(['services.surepass.token' => null]);

    expect(fetchEquifaxReport()->errorCode)->toBe(EquifaxErrorCode::NotConfigured);
    Http::assertNothingSent();

    expect(fn () => new EquifaxSubject('A B', 'AAAPZ9999Z', '9000000001', Gender::Male, CreditConsent::factory()->make(['status' => 'withdrawn'])))
        ->toThrow(InvalidArgumentException::class);
});

it('checks which Equifax APIs the token may use without sending customer data', function () {
    fakeSurepassEquifax(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    expect(app(SurepassEquifaxService::class)->checkAccess())->toBe([
        SurepassEquifaxService::REPORT_PATH => ['access' => 'allowed', 'http_status' => 400],
        SurepassEquifaxService::SCORE_PATH => ['access' => 'not_enabled', 'http_status' => 401],
        SurepassEquifaxService::PDF_PATH => ['access' => 'not_enabled', 'http_status' => 401],
    ]);
    Http::assertNotSent(fn (Request $request) => $request->data() !== []);
});
