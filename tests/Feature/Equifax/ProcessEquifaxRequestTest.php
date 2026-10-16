<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Equifax\Actions\ProcessEquifaxRequest;
use App\Modules\Equifax\Actions\ReconcileEquifaxRequest;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxReconciliationResolution;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Jobs\FetchEquifaxRequest;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Notifications\EquifaxOperationalAlert;
use App\Modules\Equifax\Services\EquifaxRoles;
use App\Modules\Equifax\Services\EquifaxSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function pendingEquifaxRequest(array $attributes = []): EquifaxCreditRequest
{
    return EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->create([
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id,
        ...$attributes,
    ]);
}

it('makes exactly one paid call per request however often the job is delivered', function () {
    fakeSurepassEquifax();
    $request = pendingEquifaxRequest();

    app(ProcessEquifaxRequest::class)->handle($request->id);
    app(ProcessEquifaxRequest::class)->handle($request->id);

    expect($request->refresh()->status)->toBe(EquifaxRequestStatus::Success)
        ->and((new FetchEquifaxRequest(1))->tries)->toBe(1);
    Http::assertSentCount(1);
});

it('sends an uncertain answer to reconciliation, keeps the lock and alerts operations', function (mixed $stub, EquifaxErrorCode $code) {
    fakeSurepassEquifax($stub);
    Setting::set(EquifaxSettings::ALERT_EMAIL, 'ops@example.test');
    Notification::fake();
    $request = pendingEquifaxRequest();

    app(ProcessEquifaxRequest::class)->handle($request->id);

    expect($request->refresh())
        ->status->toBe(EquifaxRequestStatus::ReconciliationRequired)
        ->error_code->toBe($code)
        ->in_flight_key->not->toBeNull()
        ->raw_response->toBeNull();
    Notification::assertSentTo(new AnonymousNotifiable, EquifaxOperationalAlert::class);
})->with([
    'read timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'), EquifaxErrorCode::Timeout],
    'gateway error' => [fn () => Http::response('', 504), EquifaxErrorCode::ProviderError],
    'success without report' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '700']]), EquifaxErrorCode::MissingReportPayload],
]);

it('fails definitively, releases the lock and says whether a retry is safe', function (mixed $stub, EquifaxErrorCode $code, bool $retryable) {
    fakeSurepassEquifax($stub);
    $request = pendingEquifaxRequest();

    app(ProcessEquifaxRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(EquifaxRequestStatus::Failed)->error_code->toBe($code)->retryable->toBe($retryable)->in_flight_key->toBeNull();
})->with([
    'plan lacks the API' => [fn () => Http::response(['success' => false, 'message' => 'not valid for this API/Scope'], 401), EquifaxErrorCode::ApiNotEnabled, true],
    'invalid details' => [fn () => Http::response(['message' => 'Input payload validation failed'], 400), EquifaxErrorCode::InvalidRequest, false],
]);

it('stores no score rather than a misleading one', function (string $score) {
    $body = equifaxReportFixture();
    $body['data']['credit_score'] = $score;
    $body['data']['credit_report']['credit_report'][0]['CCRResponse']['CIRReportDataLst'][0]['CIRReportData']['ScoreDetails'][0]['Value'] = $score;
    fakeSurepassEquifax(Http::response($body));
    $request = pendingEquifaxRequest();

    app(ProcessEquifaxRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(EquifaxRequestStatus::Success)->score->toBeNull();
})->with(['-1', '0', 'NH', '']);

it('sends nothing when consent was withdrawn or the integration switched off after queueing', function () {
    fakeSurepassEquifax();
    $withdrawn = pendingEquifaxRequest();
    $withdrawn->consent->update(['status' => ConsentStatus::Withdrawn]);
    app(ProcessEquifaxRequest::class)->handle($withdrawn->id);

    $switchedOff = EquifaxCreditRequest::factory()->forPan('BBBPZ1111Z')->create(['credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id]);
    Setting::set(EquifaxSettings::ENABLED, false);
    app(ProcessEquifaxRequest::class)->handle($switchedOff->id);

    expect($withdrawn->refresh()->error_code)->toBe(EquifaxErrorCode::ConsentInvalid)
        ->and($switchedOff->refresh()->error_code)->toBe(EquifaxErrorCode::NotConfigured);
    Http::assertNothingSent();
});

it('resolves a request whose job died according to whether it had been sent', function () {
    $neverSent = pendingEquifaxRequest();
    $maybeSent = EquifaxCreditRequest::factory()->forPan('BBBPZ1111Z')->processing()->create();

    (new FetchEquifaxRequest($neverSent->id))->failed(new RuntimeException('worker killed'));
    (new FetchEquifaxRequest($maybeSent->id))->failed(new RuntimeException('worker killed'));

    expect($neverSent->refresh())->status->toBe(EquifaxRequestStatus::Failed)->retryable->toBeTrue()->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(EquifaxRequestStatus::ReconciliationRequired)->in_flight_key->not->toBeNull();
});

it('reconciles an uncertain request only with the permission and a real note', function () {
    EquifaxRoles::grant();
    $request = EquifaxCreditRequest::factory()->awaitingReconciliation()->create();
    $officer = User::factory()->create()->assignRole('Credit Officer');
    $reconciler = User::factory()->create();
    $reconciler->givePermissionTo('Reconcile:EquifaxCreditRequest');

    expect(fn () => app(ReconcileEquifaxRequest::class)->handle($request, EquifaxReconciliationResolution::NotProcessed, 'Checked the Surepass dashboard', $officer))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReconcileEquifaxRequest::class)->handle($request, EquifaxReconciliationResolution::NotProcessed, 'ok', $reconciler))->toThrow(EquifaxRequestRefused::class);

    app(ReconcileEquifaxRequest::class)->handle($request, EquifaxReconciliationResolution::NotProcessed, 'Surepass usage log shows no charge', $reconciler);

    expect($request->refresh())->status->toBe(EquifaxRequestStatus::Failed)->retryable->toBeTrue()->in_flight_key->toBeNull()->reconciled_by->toBe($reconciler->id);
});
