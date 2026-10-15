<?php

use App\Models\Setting;
use App\Modules\CreditBureau\Actions\ProcessCrifReport;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Jobs\FetchCrifReport;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Notifications\CrifOperationalAlert;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function pendingCrifReport(array $attributes = []): CrifReport
{
    return CrifReport::factory()->forPan('AAAPZ9999Z')->create([
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id,
        ...$attributes,
    ]);
}

it('makes exactly one paid call per request however often the job is delivered', function () {
    fakeSurepassCrif();
    $report = pendingCrifReport();

    app(ProcessCrifReport::class)->handle($report->id);
    app(ProcessCrifReport::class)->handle($report->id);

    expect($report->refresh()->status)->toBe(CrifReportStatus::Success);
    Http::assertSentCount(1);
});

it('runs as a single-attempt job', function () {
    $job = new FetchCrifReport(1);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBeGreaterThan((int) config('services.surepass.timeout'));
});

it('sends to reconciliation and keeps the PAN locked when the outcome is uncertain', function (mixed $stub, CrifErrorCode $code) {
    fakeSurepassCrif($stub);
    Setting::set(CrifSettings::ALERT_EMAIL, 'ops@example.test');
    Notification::fake();
    $report = pendingCrifReport();

    app(ProcessCrifReport::class)->handle($report->id);

    expect($report->refresh())
        ->status->toBe(CrifReportStatus::ReconciliationRequired)
        ->error_code->toBe($code)
        ->retryable->toBeFalse()
        ->in_flight_key->toBe(PanNumber::hash('AAAPZ9999Z'))
        ->raw_response->toBeNull();
    Notification::assertSentTo(new AnonymousNotifiable, CrifOperationalAlert::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ops@example.test');
})->with([
    'read timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'), CrifErrorCode::Timeout],
    'server error' => [fn () => Http::response(['success' => false], 502), CrifErrorCode::ProviderError],
    'malformed body' => [fn () => Http::response('not json', 200), CrifErrorCode::MalformedResponse],
    'success without report' => [fn () => Http::response(['success' => true, 'data' => []], 200), CrifErrorCode::MissingReportPayload],
]);

it('fails definitively, releases the PAN and says whether a retry is safe', function (mixed $stub, CrifErrorCode $code, bool $retryable) {
    fakeSurepassCrif($stub);
    $report = pendingCrifReport();

    app(ProcessCrifReport::class)->handle($report->id);

    expect($report->refresh())
        ->status->toBe(CrifReportStatus::Failed)
        ->error_code->toBe($code)
        ->error_message->toBe($code->safeMessage())
        ->retryable->toBe($retryable)
        ->in_flight_key->toBeNull();
})->with([
    'refused connection' => [fn () => throw new ConnectionException('cURL error 7: Failed to connect'), CrifErrorCode::ConnectionFailed, true],
    'bad token' => [fn () => Http::response(['success' => false], 401), CrifErrorCode::AuthenticationFailed, true],
    'invalid details' => [fn () => Http::response(['success' => false, 'message_code' => 'invalid_pan'], 422), CrifErrorCode::InvalidRequest, false],
]);

it('stores no score rather than a misleading one when CRIF sends an invalid value', function (string $score) {
    $body = crifReportFixture();
    $body['data']['credit_score'] = $score;
    $body['data']['credit_report']['SCORES']['SCORE']['SCORE-VALUE'] = $score;
    fakeSurepassCrif(Http::response($body));
    $report = pendingCrifReport();

    app(ProcessCrifReport::class)->handle($report->id);

    expect($report->refresh())->status->toBe(CrifReportStatus::Success)->score->toBeNull();
})->with(['-1', '0', 'NH', '1200', '']);

it('sends nothing when consent was withdrawn after the request was queued', function () {
    fakeSurepassCrif();
    $report = pendingCrifReport();
    $report->consent->update(['status' => ConsentStatus::Withdrawn]);

    app(ProcessCrifReport::class)->handle($report->id);

    expect($report->refresh())->status->toBe(CrifReportStatus::Failed)->error_code->toBe(CrifErrorCode::ConsentInvalid);
    Http::assertNothingSent();
});

it('sends nothing when the integration was switched off after the request was queued', function () {
    fakeSurepassCrif();
    $report = pendingCrifReport();
    Setting::set(CrifSettings::ENABLED, false);

    app(ProcessCrifReport::class)->handle($report->id);

    expect($report->refresh())->status->toBe(CrifReportStatus::Failed)->retryable->toBeTrue();
    Http::assertNothingSent();
});

it('resolves a request whose job died according to whether it had been sent', function (string $state, CrifReportStatus $expected, bool $locked) {
    $report = $state === 'pending' ? pendingCrifReport() : pendingCrifReport()->forceFill(['status' => CrifReportStatus::Processing]);
    $report->save();

    (new FetchCrifReport($report->id))->failed(new RuntimeException('worker killed'));

    expect($report->refresh()->status)->toBe($expected)
        ->and($report->in_flight_key !== null)->toBe($locked);
})->with([
    'never sent' => ['pending', CrifReportStatus::Failed, false],
    'maybe sent' => ['processing', CrifReportStatus::ReconciliationRequired, true],
]);
