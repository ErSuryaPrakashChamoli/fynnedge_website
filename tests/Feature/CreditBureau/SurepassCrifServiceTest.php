<?php

use App\Modules\CreditBureau\DataTransferObjects\CrifSubject;
use App\Modules\CreditBureau\DataTransferObjects\SurepassCrifResult;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\SurepassCrifService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function crifSubject(): CrifSubject
{
    return new CrifSubject('Test', 'Fixture', '9000000001', 'AAAPZ9999Z', CreditConsent::factory()->make());
}

function fetchCrif(): SurepassCrifResult
{
    return app(SurepassCrifService::class)->fetchReport(crifSubject());
}

it('posts the documented fetch-report body with the bearer token', function () {
    fakeSurepassCrif();

    fetchCrif();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://kyc-api.surepass.app/api/v1/credit-report-crif/fetch-report'
        && $request->hasHeader('Authorization', 'Bearer test-surepass-token')
        && $request->hasHeader('Content-Type', 'application/json')
        && $request->data() === [
            'first_name' => 'Test',
            'last_name' => 'Fixture',
            'mobile' => '9000000001',
            'pan' => 'AAAPZ9999Z',
            'consent' => 'Y',
        ]);
});

it('posts the PDF request with raw set to false and the score request to its own endpoint', function () {
    fakeSurepassCrif(Http::response(['success' => true, 'data' => ['credit_report_link' => 'https://files.example.test/r.pdf', 'credit_score' => '742']]));

    app(SurepassCrifService::class)->fetchReportPdf(crifSubject());
    app(SurepassCrifService::class)->fetchScore(crifSubject());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/fetch-report-pdf') && $request['raw'] === false && $request['consent'] === 'Y');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-crif/score') && $request['pan'] === 'AAAPZ9999Z');
});

it('applies the configured timeouts and never retries', function () {
    $calls = 0;
    $options = null;
    fakeSurepassCrif(function (Request $request, array $requestOptions) use (&$calls, &$options): PromiseInterface {
        $calls++;
        $options = $requestOptions;

        return Http::response(['success' => false, 'message_code' => 'server_error'], 500);
    });
    config(['services.surepass.timeout' => 45, 'services.surepass.connect_timeout' => 8]);

    fetchCrif();

    expect($calls)->toBe(1)
        ->and($options)->timeout->toBe(45)->connect_timeout->toBe(8);
});

it('refuses to build a subject without a given consent', function () {
    new CrifSubject('Test', 'Fixture', '9000000001', 'AAAPZ9999Z', CreditConsent::factory()->make(['status' => ConsentStatus::Withdrawn]));
})->throws(InvalidArgumentException::class);

it('sends nothing when no token is configured', function () {
    fakeSurepassCrif();
    config(['services.surepass.token' => null]);

    expect(fetchCrif()->errorCode)->toBe(CrifErrorCode::NotConfigured);
    Http::assertNothingSent();
});

it('returns the complete body for a successful report', function () {
    fakeSurepassCrif();

    $result = fetchCrif();

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->body)->toBe(crifReportFixture())
        ->and($result->httpStatus)->toBe(200);
});

it('classifies each provider answer and keeps failed bodies out of the result', function (mixed $stub, CrifErrorCode $code, bool $uncertain) {
    fakeSurepassCrif($stub);

    $result = fetchCrif();

    expect($result->errorCode)->toBe($code)
        ->and($result->isUncertain())->toBe($uncertain)
        ->and($result->body)->toBeNull();
})->with([
    'invalid token' => [fn () => Http::response(['success' => false, 'message_code' => 'unauthorized'], 401), CrifErrorCode::AuthenticationFailed, false],
    'insufficient balance (402)' => [fn () => Http::response(['success' => false], 402), CrifErrorCode::InsufficientBalance, false],
    'insufficient balance (message code)' => [fn () => Http::response(['success' => false, 'message_code' => 'insufficient_balance'], 200), CrifErrorCode::InsufficientBalance, false],
    'rate limited' => [fn () => Http::response(['success' => false], 429), CrifErrorCode::RateLimited, false],
    'invalid pan (422)' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_pan'], 422), CrifErrorCode::InvalidRequest, false],
    'no record found' => [fn () => Http::response(['success' => false, 'message_code' => 'record_not_found'], 422), CrifErrorCode::NoRecordFound, false],
    'provider-level failure despite 200' => [fn () => Http::response(['success' => false, 'message_code' => 'verification_failed'], 200), CrifErrorCode::ProviderRejected, false],
    'service unavailable' => [fn () => Http::response('down', 503), CrifErrorCode::ProviderUnavailable, false],
    'server error' => [fn () => Http::response(['success' => false], 500), CrifErrorCode::ProviderError, true],
    'gateway timeout' => [fn () => Http::response('', 504), CrifErrorCode::ProviderError, true],
    'malformed JSON' => [fn () => Http::response('<html>oops</html>', 200), CrifErrorCode::MalformedResponse, true],
    '200 without a success flag' => [fn () => Http::response(['data' => ['credit_report' => ['HEADER' => []]]], 200), CrifErrorCode::MalformedResponse, true],
    'success without a report' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '742']], 200), CrifErrorCode::MissingReportPayload, true],
    'success with an empty report' => [fn () => Http::response(['success' => true, 'data' => ['credit_report' => []]], 200), CrifErrorCode::MissingReportPayload, true],
]);

it('treats a read timeout as uncertain but a refused connection as safe to retry', function (string $message, CrifErrorCode $code) {
    fakeSurepassCrif(fn () => throw new ConnectionException($message));

    expect(fetchCrif()->errorCode)->toBe($code);
})->with([
    'read timeout' => ['cURL error 28: Operation timed out after 60001 milliseconds with 0 bytes received', CrifErrorCode::Timeout],
    'connect timeout' => ['cURL error 28: Connection timed out after 10001 milliseconds', CrifErrorCode::ConnectionFailed],
    'refused' => ['cURL error 7: Failed to connect to kyc-api.surepass.app port 443', CrifErrorCode::ConnectionFailed],
    'dns' => ['cURL error 6: Could not resolve host: kyc-api.surepass.app', CrifErrorCode::ConnectionFailed],
    'dropped after sending' => ['cURL error 56: Recv failure: Connection reset by peer', CrifErrorCode::UnexpectedError],
]);

it('downloads the PDF without sending the Surepass token to the file host', function () {
    fakeSurepassCrif();
    Http::fake(['files.example.test/*' => Http::response('%PDF-1.7 fixture', 200, ['Content-Type' => 'application/pdf'])]);

    $bytes = app(SurepassCrifService::class)->downloadPdf('https://files.example.test/report.pdf');

    expect($bytes)->toBe('%PDF-1.7 fixture');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://files.example.test/report.pdf' && ! $request->hasHeader('Authorization'));
});

it('accepts only https PDF links and only real PDF bytes', function () {
    fakeSurepassCrif();
    Http::fake(['files.example.test/*' => Http::response('<html>expired link</html>', 200)]);
    $service = app(SurepassCrifService::class);

    expect($service->pdfLink(['credit_report_link' => 'http://files.example.test/r.pdf']))->toBeNull()
        ->and($service->pdfLink(['pdf_link' => 'https://files.example.test/r.pdf']))->toBe('https://files.example.test/r.pdf')
        ->and($service->downloadPdf('https://files.example.test/r.pdf'))->toBeNull();
});
