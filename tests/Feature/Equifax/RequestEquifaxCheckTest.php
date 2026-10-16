<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Actions\RequestEquifaxCheck;
use App\Modules\Equifax\DataTransferObjects\EquifaxCheckRequest;
use App\Modules\Equifax\DataTransferObjects\EquifaxOutcome;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxCreditService;
use App\Modules\Equifax\Services\EquifaxFingerprint;
use App\Modules\Equifax\Services\EquifaxRoles;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

beforeEach(function () {
    CrifRoles::grant();
    EquifaxRoles::grant();
    $this->officer = User::factory()->create()->assignRole(CrifRoles::OFFICER_ROLE);
    $this->customer = Customer::factory()->create();
});

function requestEquifax(EquifaxCheckRequest $request, ?User $actor = null): EquifaxOutcome
{
    return app(RequestEquifaxCheck::class)->handle($request, $actor ?? test()->officer);
}

it('fetches, stores and indexes a full report, keeping the complete response and a separate summary', function () {
    fakeSurepassEquifax();

    $outcome = requestEquifax(equifaxRequestFor($this->customer, ['pan' => ' aaapz 9999z ']));
    $request = $outcome->request->refresh();

    expect($outcome->kind)->toBe(EquifaxOutcome::QUEUED)
        ->and($request)
        ->status->toBe(EquifaxRequestStatus::Success)
        ->product->toBe(EquifaxProduct::Report)
        ->score->toBe(761)
        ->provider_report_id->toBe('FIXTURE-ORDER-0001')
        ->provider_client_id->toBe('credit_report_v2_TESTFIXTURE0001')
        ->pan_masked->toBe('XXXXXX999Z')
        ->mobile_masked->toBe('******0001')
        ->gender->toBe(Gender::Male)
        ->in_flight_key->toBeNull()
        ->requested_by->toBe($this->officer->id)
        ->and($request->report_date->toDateString())->toBe('2026-10-16')
        ->and($request->raw_response)->toBe(equifaxReportFixture())
        ->and($request->summary)->toMatchArray(['score' => 761, 'accounts_total' => '2']);
});

it('stores the PAN, name, mobile, response and summary encrypted and out of serialisation', function () {
    fakeSurepassEquifax();

    $request = requestEquifax(equifaxRequestFor($this->customer))->request;
    $row = (array) DB::table('equifax_credit_requests')->find($request->id);

    expect($row['pan_encrypted'])->not->toContain('AAAPZ9999Z')
        ->and($row['full_name'])->not->toContain('Test Fixture')
        ->and($row['mobile'])->not->toContain('9000000001')
        ->and($row['raw_response'])->not->toContain('FIXTURE BANK')
        ->and($row['summary'])->not->toContain('761')
        ->and($request->refresh()->toArray())->not->toHaveKeys(['pan_encrypted', 'full_name', 'mobile', 'raw_response', 'summary', 'pan_hash']);
});

it('fetches a score-only result from the score endpoint', function () {
    fakeSurepassEquifax(Http::response(['success' => true, 'data' => ['client_id' => 'credit_report_v2_score_TEST', 'credit_score' => '702']]));

    $request = requestEquifax(equifaxRequestFor($this->customer, ['product' => EquifaxProduct::Score]))->request->refresh();

    expect($request)->product->toBe(EquifaxProduct::Score)->score->toBe(702)->status->toBe(EquifaxRequestStatus::Success);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-v2/score') && $request['report_type'] === 'json');
});

it('refuses without valid consent and sends nothing', function (Closure $consent, string $message) {
    fakeSurepassEquifax();

    expect(fn () => requestEquifax(equifaxRequestFor($this->customer, ['consent' => $consent($this->customer)])))
        ->toThrow(EquifaxRequestRefused::class, $message);

    expect(EquifaxCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'not attested' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-1', now(), false), 'Confirm that the customer consented'],
    'no evidence' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::WrittenForm, '', now(), true), 'evidence reference'],
    'too old' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::Email, 'MAIL-1', now()->subDays(31), true), 'within the last 30 days'],
    'withdrawn' => [fn (Customer $customer) => CreditConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'status' => ConsentStatus::Withdrawn])), 'withdrawn'],
    'another customer' => [fn () => CreditConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => Customer::factory()->create()->id])), 'different customer'],
]);

it('rejects invalid details before anything is stored or sent', function (array $overrides, string $message) {
    fakeSurepassEquifax();

    expect(fn () => requestEquifax(equifaxRequestFor($this->customer, $overrides)))->toThrow(EquifaxRequestRefused::class, $message);

    expect(EquifaxCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'bad PAN' => [['pan' => 'ABCD1234F'], 'valid PAN'],
    'no name' => [['name' => ' '], 'full name'],
    'bad mobile' => [['mobile' => '0123'], '10-digit'],
    'no gender' => [['gender' => null], 'gender'],
]);

it('refuses when switched off, without a token, or without the permission', function () {
    fakeSurepassEquifax();
    Setting::set(EquifaxSettings::ENABLED, false);
    expect(fn () => requestEquifax(equifaxRequestFor($this->customer)))->toThrow(EquifaxRequestRefused::class, 'switched off');

    Setting::set(EquifaxSettings::ENABLED, true);
    config(['services.surepass.token' => '']);
    expect(fn () => requestEquifax(equifaxRequestFor($this->customer)))->toThrow(EquifaxRequestRefused::class, 'not configured');

    config(['services.surepass.token' => 'test-surepass-token']);
    expect(fn () => requestEquifax(equifaxRequestFor($this->customer), User::factory()->create()))->toThrow(AuthorizationException::class);
    Http::assertNothingSent();
});

it('blocks an equivalent request while one is in flight, and lets a report proceed past an in-flight score', function () {
    fakeSurepassEquifax();
    $inFlightReport = EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->processing()->create();

    expect(requestEquifax(equifaxRequestFor($this->customer))->kind)->toBe(EquifaxOutcome::BLOCKED)
        ->and(requestEquifax(equifaxRequestFor($this->customer, ['product' => EquifaxProduct::Score]))->kind)->toBe(EquifaxOutcome::BLOCKED)
        ->and($inFlightReport->refresh()->duplicates_blocked)->toBe(2);
    Http::assertNothingSent();

    $inFlightReport->forceFill(['status' => EquifaxRequestStatus::Failed, 'in_flight_key' => null, 'retryable' => true])->save();
    EquifaxCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->processing()->create();

    expect(requestEquifax(equifaxRequestFor($this->customer))->kind)->toBe(EquifaxOutcome::QUEUED);
});

it('reuses results by product: a report answers a report or a score, a score never answers a report', function () {
    fakeSurepassEquifax();
    $score = EquifaxCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->successful()->create();

    expect(requestEquifax(equifaxRequestFor($this->customer, ['product' => EquifaxProduct::Score]))->request->is($score))->toBeTrue();
    expect(requestEquifax(equifaxRequestFor($this->customer))->kind)->toBe(EquifaxOutcome::QUEUED);

    $reused = requestEquifax(equifaxRequestFor($this->customer, ['product' => EquifaxProduct::Score]));
    expect($reused->kind)->toBe(EquifaxOutcome::REUSED)
        ->and($reused->request->product)->toBe(EquifaxProduct::Report)
        ->and(requestEquifax(equifaxRequestFor($this->customer))->kind)->toBe(EquifaxOutcome::REUSED);

    Http::assertSentCount(1);
});

it('applies separate reuse periods to scores and full reports', function () {
    fakeSurepassEquifax(Http::response(['success' => true, 'data' => ['credit_score' => '700']]));
    Setting::set(EquifaxSettings::SCORE_REUSE_DAYS, 7);
    Setting::set(EquifaxSettings::REPORT_REUSE_DAYS, 60);
    EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create(['completed_at' => now()->subDays(10)]);

    expect(requestEquifax(equifaxRequestFor($this->customer))->kind)->toBe(EquifaxOutcome::REUSED)
        ->and(requestEquifax(equifaxRequestFor($this->customer, ['product' => EquifaxProduct::Score]))->kind)->toBe(EquifaxOutcome::QUEUED);
});

it('never reuses a CRIF report as an Equifax result', function () {
    fakeSurepassEquifax();
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create();

    expect(requestEquifax(equifaxRequestFor($this->customer))->kind)->toBe(EquifaxOutcome::QUEUED);
    Http::assertSentCount(1);
});

it('lets an authorised user pull a fresh report with a recorded reason, and refuses others', function () {
    fakeSurepassEquifax();
    EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create();
    $fresh = ['type' => EquifaxRequestType::Fresh, 'freshReason' => 'Lender requires a report dated this week'];

    expect(fn () => requestEquifax(equifaxRequestFor($this->customer, $fresh)))->toThrow(AuthorizationException::class);

    $manager = User::factory()->create();
    $manager->givePermissionTo('FetchFresh:EquifaxCreditRequest');
    expect(fn () => requestEquifax(equifaxRequestFor($this->customer, [...$fresh, 'freshReason' => 'needed']), $manager))->toThrow(EquifaxRequestRefused::class, '15 characters');

    $outcome = requestEquifax(equifaxRequestFor($this->customer, $fresh), $manager);
    expect($outcome->kind)->toBe(EquifaxOutcome::QUEUED)
        ->and($outcome->request->refresh())->request_type->toBe(EquifaxRequestType::Fresh)->fresh_reason->toBe('Lender requires a report dated this week')
        ->and(EquifaxCreditRequest::query()->count())->toBe(2);
});

it('refuses to resend details Surepass rejected, but accepts corrected ones', function () {
    fakeSurepassEquifax();
    EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->failed(EquifaxErrorCode::NoRecordFound)->create([
        'request_fingerprint' => EquifaxFingerprint::request('AAAPZ9999Z', 'Test Fixture', '9000000001', 'male'),
    ]);

    expect(fn () => requestEquifax(equifaxRequestFor($this->customer)))->toThrow(EquifaxRequestRefused::class, 'No Equifax record found');
    expect(requestEquifax(equifaxRequestFor($this->customer, ['gender' => Gender::Female]))->kind)->toBe(EquifaxOutcome::QUEUED);
});

it('retries an unprocessed failure once and refuses to retry one that may have been charged', function () {
    fakeSurepassEquifax();
    $consentId = CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $this->customer->id])->id;
    $failed = EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->failed(EquifaxErrorCode::ApiNotEnabled)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => $consentId]);
    $rejected = EquifaxCreditRequest::factory()->forPan('BBBPZ1111Z')->failed(EquifaxErrorCode::InvalidRequest)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => $consentId]);

    $retry = requestEquifax(EquifaxCheckRequest::retryOf($failed))->request->refresh();
    expect($retry)->request_type->toBe(EquifaxRequestType::Retry)->previous_request_id->toBe($failed->id)->status->toBe(EquifaxRequestStatus::Success);

    expect(fn () => requestEquifax(EquifaxCheckRequest::retryOf($rejected)))->toThrow(EquifaxRequestRefused::class);
    Http::assertSentCount(1);
});

it('lets the database hold only one unresolved request per PAN and product', function () {
    EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->create();

    expect(fn () => EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->processing()->create())
        ->toThrow(UniqueConstraintViolationException::class);

    EquifaxCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->create();
    expect(EquifaxCreditRequest::query()->forPan(PanNumber::hash('AAAPZ9999Z'))->inFlight()->count())->toBe(2);
});

it('refuses a request that arrives while another for the same PAN is being started', function () {
    fakeSurepassEquifax();
    Sleep::fake(syncWithCarbon: true);
    $lock = Cache::lock('equifax-request:'.PanNumber::hash('AAAPZ9999Z'), 30);
    $lock->get();

    expect(fn () => requestEquifax(equifaxRequestFor($this->customer)))->toThrow(EquifaxRequestRefused::class, 'being started right now');

    Http::assertNothingSent();
    $lock->release();
});

it('gives other modules a read-only view that never calls Surepass', function () {
    fakeSurepassEquifax();
    $service = app(EquifaxCreditService::class);
    expect($service->latestScore($this->customer))->toBeNull()->and($service->summary($this->customer))->toBeNull();

    requestEquifax(equifaxRequestFor($this->customer));

    expect($service->latestScore($this->customer))->toMatchArray(['score' => 761, 'product' => 'report', 'reusable' => true])
        ->and($service->summary($this->customer))->toMatchArray(['accounts_total' => '2', 'reusable' => true]);
    Http::assertSentCount(1);
});

it('keeps the PAN, mobile, name, score and report out of logs and the audit trail', function () {
    fakeSurepassEquifax();
    Log::spy();

    $request = requestEquifax(equifaxRequestFor($this->customer))->request;
    $audit = json_encode($request->auditLogs()->pluck('changes'));

    expect($audit)->not->toContain('AAAPZ9999Z')->not->toContain('9000000001')->not->toContain('Test Fixture')->not->toContain('FIXTURE BANK')->not->toContain('"score"');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), 'AAAPZ9999Z')
        && ! str_contains(json_encode($context), '9000000001')
        && ! str_contains(json_encode($context), 'test-surepass-token'))->atLeast()->once();
});

it('links a request to the application it came from', function () {
    fakeSurepassEquifax();
    $session = JourneySession::factory()->create(['customer_id' => $this->customer->id]);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    $request = requestEquifax(equifaxRequestFor($this->customer, ['consent' => CreditConsentInput::existing($consent), 'journeySession' => $session]))->request;

    expect($request)->journey_session_id->toBe($session->id)->credit_consent_id->toBe($consent->id)
        ->and($session->equifaxRequests()->count())->toBe(1);
});
