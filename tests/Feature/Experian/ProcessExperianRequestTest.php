<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Experian\Actions\ProcessExperianRequest;
use App\Modules\Experian\Actions\ReconcileExperianRequest;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianReconciliationResolution;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Jobs\FetchExperianRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Notifications\ExperianOperationalAlert;
use App\Modules\Experian\Services\ExperianRoles;
use App\Modules\Experian\Services\ExperianSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function pendingExperianRequest(array $attributes = [], ?Closure $state = null): ExperianCreditRequest
{
    $factory = ExperianCreditRequest::factory();

    return ($state ? $state($factory) : $factory)->forPan('AAAPZ9999Z')->create([
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id,
        ...$attributes,
    ]);
}

it('makes exactly one paid call per request however often the job is delivered', function () {
    fakeSurepassExperian();
    $request = pendingExperianRequest();

    app(ProcessExperianRequest::class)->handle($request->id);
    app(ProcessExperianRequest::class)->handle($request->id);

    expect($request->refresh()->status)->toBe(ExperianRequestStatus::Success)
        ->and((new FetchExperianRequest(1))->tries)->toBe(1);
    Http::assertSentCount(1);
});

it('sends an uncertain answer to reconciliation, keeps the lock and alerts operations', function (mixed $stub, ExperianErrorCode $code, ?Closure $state) {
    fakeSurepassExperian($stub);
    Setting::set(ExperianSettings::ALERT_EMAIL, 'ops@example.test');
    Notification::fake();
    $request = pendingExperianRequest(state: $state);

    app(ProcessExperianRequest::class)->handle($request->id);

    expect($request->refresh())
        ->status->toBe(ExperianRequestStatus::ReconciliationRequired)
        ->error_code->toBe($code)
        ->in_flight_key->not->toBeNull()
        ->raw_response->toBeNull();
    Notification::assertSentTo(new AnonymousNotifiable, ExperianOperationalAlert::class);
})->with([
    'read timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'), ExperianErrorCode::Timeout, null],
    'server error' => [fn () => Http::response('', 500), ExperianErrorCode::ProviderError, null],
    'success without report' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '700']]), ExperianErrorCode::MissingReportPayload, null],
    'score success without score' => [fn () => Http::response(['success' => true, 'data' => ['client_id' => 'x']]), ExperianErrorCode::MissingScore, fn ($factory) => $factory->score()],
    'PDF success without link' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '761', 'credit_report' => []]]), ExperianErrorCode::MissingPdfLink, fn ($factory) => $factory->pdf()],
]);

it('fails definitively, releases the lock and says whether a retry is safe', function (mixed $stub, ExperianErrorCode $code, bool $retryable) {
    fakeSurepassExperian($stub);
    $request = pendingExperianRequest();

    app(ProcessExperianRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(ExperianRequestStatus::Failed)->error_code->toBe($code)->retryable->toBe($retryable)->in_flight_key->toBeNull();
})->with([
    'plan lacks the API' => [fn () => Http::response(['success' => false, 'message' => 'not valid for this API/Scope'], 401), ExperianErrorCode::ApiNotEnabled, true],
    'rate limited' => [fn () => Http::response(['message' => 'slow down'], 429), ExperianErrorCode::RateLimited, true],
    'invalid details' => [fn () => Http::response(['message' => 'Input payload validation failed'], 400), ExperianErrorCode::InvalidRequest, false],
]);

it('stores no score rather than a misleading one', function (string $score) {
    $body = experianFixture();
    $body['data']['credit_score'] = $score;
    $body['data']['credit_report']['SCORE']['BureauScore'] = $score;
    fakeSurepassExperian(Http::response($body));
    $request = pendingExperianRequest();

    app(ProcessExperianRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(ExperianRequestStatus::Success)->score->toBeNull();
})->with(['-1', '0', 'NH', '']);

it('sends nothing when consent was withdrawn or the integration switched off after queueing', function () {
    fakeSurepassExperian();
    $withdrawn = pendingExperianRequest();
    $withdrawn->consent->update(['status' => ConsentStatus::Withdrawn]);
    app(ProcessExperianRequest::class)->handle($withdrawn->id);

    $switchedOff = ExperianCreditRequest::factory()->forPan('BBBPZ1111Z')->create(['credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id]);
    Setting::set(ExperianSettings::ENABLED, false);
    app(ProcessExperianRequest::class)->handle($switchedOff->id);

    expect($withdrawn->refresh()->error_code)->toBe(ExperianErrorCode::ConsentInvalid)
        ->and($switchedOff->refresh()->error_code)->toBe(ExperianErrorCode::NotConfigured);
    Http::assertNothingSent();
});

it('resolves a request whose job died according to whether it had been sent', function () {
    $neverSent = pendingExperianRequest();
    $maybeSent = ExperianCreditRequest::factory()->forPan('BBBPZ1111Z')->processing()->create();

    (new FetchExperianRequest($neverSent->id))->failed(new RuntimeException('worker killed'));
    (new FetchExperianRequest($maybeSent->id))->failed(new RuntimeException('worker killed'));

    expect($neverSent->refresh())->status->toBe(ExperianRequestStatus::Failed)->retryable->toBeTrue()->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(ExperianRequestStatus::ReconciliationRequired)->in_flight_key->not->toBeNull();
});

it('reconciles an uncertain request only with the permission and a real note', function () {
    ExperianRoles::grant();
    $request = ExperianCreditRequest::factory()->awaitingReconciliation()->create();
    $officer = User::factory()->create()->assignRole('Credit Officer');
    $reconciler = User::factory()->create();
    $reconciler->givePermissionTo('Reconcile:ExperianCreditRequest');

    expect(fn () => app(ReconcileExperianRequest::class)->handle($request, ExperianReconciliationResolution::NotProcessed, 'Checked the Surepass dashboard', $officer))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReconcileExperianRequest::class)->handle($request, ExperianReconciliationResolution::NotProcessed, 'ok', $reconciler))->toThrow(ExperianRequestRefused::class);

    app(ReconcileExperianRequest::class)->handle($request, ExperianReconciliationResolution::NotProcessed, 'Surepass usage log shows no charge', $reconciler);

    expect($request->refresh())->status->toBe(ExperianRequestStatus::Failed)->retryable->toBeTrue()->in_flight_key->toBeNull()->reconciled_by->toBe($reconciler->id);
});
