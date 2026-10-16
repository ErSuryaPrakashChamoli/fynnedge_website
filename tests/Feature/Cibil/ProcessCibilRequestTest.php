<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Actions\ProcessCibilRequest;
use App\Modules\Cibil\Actions\ReconcileCibilRequest;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilReconciliationResolution;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Jobs\FetchCibilRequest;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Notifications\CibilOperationalAlert;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function pendingCibilRequest(array $attributes = [], ?Closure $state = null): CibilCreditRequest
{
    $factory = CibilCreditRequest::factory();

    return ($state ? $state($factory) : $factory)->forPan('AAAPZ9999Z')->create([
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id,
        ...$attributes,
    ]);
}

it('makes exactly one paid call per request however often the job is delivered', function () {
    fakeSurepassCibil();
    $request = pendingCibilRequest();

    app(ProcessCibilRequest::class)->handle($request->id);
    app(ProcessCibilRequest::class)->handle($request->id);

    expect($request->refresh()->status)->toBe(CibilRequestStatus::Success)
        ->and((new FetchCibilRequest(1))->tries)->toBe(1);
    Http::assertSentCount(1);
});

it('sends an uncertain answer to reconciliation, keeps the lock and alerts operations', function (mixed $stub, CibilErrorCode $code, ?Closure $state) {
    fakeSurepassCibil($stub);
    Setting::set(CibilSettings::ALERT_EMAIL, 'ops@example.test');
    Notification::fake();
    $request = pendingCibilRequest(state: $state);

    app(ProcessCibilRequest::class)->handle($request->id);

    expect($request->refresh())
        ->status->toBe(CibilRequestStatus::ReconciliationRequired)
        ->error_code->toBe($code)
        ->in_flight_key->not->toBeNull()
        ->raw_response->toBeNull();
    Notification::assertSentTo(new AnonymousNotifiable, CibilOperationalAlert::class);
})->with([
    'read timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'), CibilErrorCode::Timeout, null],
    'server error' => [fn () => Http::response('', 500), CibilErrorCode::ProviderError, null],
    'success without report' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '700']]), CibilErrorCode::MissingReportPayload, null],
    'score success without score' => [fn () => Http::response(['success' => true, 'data' => ['client_id' => 'x']]), CibilErrorCode::MissingScore, fn ($factory) => $factory->score()],
    'PDF success without link' => [fn () => Http::response(['success' => true, 'data' => ['credit_score' => '761', 'credit_report' => []]]), CibilErrorCode::MissingPdfLink, fn ($factory) => $factory->pdf()],
]);

it('fails definitively, releases the lock and says whether a retry is safe', function (mixed $stub, CibilErrorCode $code, bool $retryable) {
    fakeSurepassCibil($stub);
    $request = pendingCibilRequest();

    app(ProcessCibilRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(CibilRequestStatus::Failed)->error_code->toBe($code)->retryable->toBe($retryable)->in_flight_key->toBeNull();
})->with([
    'plan lacks the API' => [fn () => Http::response(['success' => false, 'message' => 'not valid for this API/Scope'], 401), CibilErrorCode::ApiNotEnabled, true],
    'rate limited' => [fn () => Http::response(['message' => 'slow down'], 429), CibilErrorCode::RateLimited, true],
    'invalid details' => [fn () => Http::response(['message' => 'Input payload validation failed'], 400), CibilErrorCode::InvalidRequest, false],
]);

it('stores no score rather than a misleading one', function (string $score) {
    $body = cibilFixture();
    $body['data']['credit_score'] = $score;
    $body['data']['credit_report'][0]['scores'][0]['score'] = $score;
    fakeSurepassCibil(Http::response($body));
    $request = pendingCibilRequest();

    app(ProcessCibilRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(CibilRequestStatus::Success)->score->toBeNull();
})->with(['-1', '000-1', '0', '5', 'NH', '']);

it('sends nothing when consent was withdrawn or the integration switched off after queueing', function () {
    fakeSurepassCibil();
    $withdrawn = pendingCibilRequest();
    $withdrawn->consent->update(['status' => ConsentStatus::Withdrawn]);
    app(ProcessCibilRequest::class)->handle($withdrawn->id);

    $switchedOff = CibilCreditRequest::factory()->forPan('BBBPZ1111Z')->create(['credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null])->id]);
    Setting::set(CibilSettings::ENABLED, false);
    app(ProcessCibilRequest::class)->handle($switchedOff->id);

    expect($withdrawn->refresh()->error_code)->toBe(CibilErrorCode::ConsentInvalid)
        ->and($switchedOff->refresh()->error_code)->toBe(CibilErrorCode::NotConfigured);
    Http::assertNothingSent();
});

it('resolves a request whose job died according to whether it had been sent', function () {
    $neverSent = pendingCibilRequest();
    $maybeSent = CibilCreditRequest::factory()->forPan('BBBPZ1111Z')->processing()->create();

    (new FetchCibilRequest($neverSent->id))->failed(new RuntimeException('worker killed'));
    (new FetchCibilRequest($maybeSent->id))->failed(new RuntimeException('worker killed'));

    expect($neverSent->refresh())->status->toBe(CibilRequestStatus::Failed)->retryable->toBeTrue()->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(CibilRequestStatus::ReconciliationRequired)->in_flight_key->not->toBeNull();
});

it('reconciles an uncertain request only with the permission and a real note', function () {
    CibilRoles::grant();
    $request = CibilCreditRequest::factory()->awaitingReconciliation()->create();
    $officer = User::factory()->create()->assignRole('Credit Officer');
    $reconciler = User::factory()->create();
    $reconciler->givePermissionTo('Reconcile:CibilCreditRequest');

    expect(fn () => app(ReconcileCibilRequest::class)->handle($request, CibilReconciliationResolution::NotProcessed, 'Checked the Surepass dashboard', $officer))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReconcileCibilRequest::class)->handle($request, CibilReconciliationResolution::NotProcessed, 'ok', $reconciler))->toThrow(CibilRequestRefused::class);

    app(ReconcileCibilRequest::class)->handle($request, CibilReconciliationResolution::NotProcessed, 'Surepass usage log shows no charge', $reconciler);

    expect($request->refresh())->status->toBe(CibilRequestStatus::Failed)->retryable->toBeTrue()->in_flight_key->toBeNull()->reconciled_by->toBe($reconciler->id);
});

it('stores an inline Base64 PDF as an encrypted file and keeps it out of the stored JSON', function () {
    Storage::fake(FetchCibilPdf::DISK);
    $body = cibilFixture('pdf');
    $body['data']['credit_report_link'] = null;
    $body['data']['credit_report_base64'] = base64_encode('%PDF-1.4 inline synthetic CIBIL PDF');
    fakeSurepassCibil(Http::response($body));
    $request = pendingCibilRequest(state: fn ($factory) => $factory->pdf());

    app(ProcessCibilRequest::class)->handle($request->id);

    $request->refresh();
    expect($request)->status->toBe(CibilRequestStatus::Success)->score->toBe(750)->pdf_error_code->toBeNull()
        ->and($request->hasStoredPdf())->toBeTrue()
        ->and(Crypt::decryptString(Storage::disk(FetchCibilPdf::DISK)->get($request->pdf_path)))->toBe('%PDF-1.4 inline synthetic CIBIL PDF')
        ->and($request->raw_response['data']['credit_report_base64'])->toBe('[stored as an encrypted PDF file]')
        ->and(json_encode($request->raw_response))->not->toContain(base64_encode('%PDF-1.4 inline synthetic CIBIL PDF'));
    Http::assertSentCount(1);
});

it('refuses a stored request that has no gender without calling Surepass', function () {
    fakeSurepassCibil();
    $request = pendingCibilRequest(['gender' => null]);

    app(ProcessCibilRequest::class)->handle($request->id);

    expect($request->refresh())->status->toBe(CibilRequestStatus::Failed)->error_code->toBe(CibilErrorCode::InvalidRequest);
    Http::assertNothingSent();
});
