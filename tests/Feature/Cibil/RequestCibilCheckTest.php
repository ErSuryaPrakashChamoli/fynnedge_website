<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Actions\RequestCibilCheck;
use App\Modules\Cibil\DataTransferObjects\CibilCheckRequest;
use App\Modules\Cibil\DataTransferObjects\CibilOutcome;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Enums\CibilRequestType;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilCreditService;
use App\Modules\Cibil\Services\CibilFingerprint;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    CrifRoles::grant();
    CibilRoles::grant();
    $this->officer = User::factory()->create()->assignRole(CrifRoles::OFFICER_ROLE);
    $this->customer = Customer::factory()->create();
});

function requestCibil(CibilCheckRequest $request, ?User $actor = null): CibilOutcome
{
    return app(RequestCibilCheck::class)->handle($request, $actor ?? test()->officer);
}

it('fetches, stores and indexes a full report, keeping the complete response and a separate summary', function () {
    fakeSurepassCibil();

    $outcome = requestCibil(cibilRequestFor($this->customer, ['pan' => ' aaapz 9999z ']));
    $request = $outcome->request->refresh();

    expect($outcome->kind)->toBe(CibilOutcome::QUEUED)
        ->and($request)
        ->status->toBe(CibilRequestStatus::Success)
        ->product->toBe(CibilProduct::Report)
        ->score->toBe(750)
        ->provider_report_id->toBe('9100000001')
        ->provider_client_id->toBe('credit_report_cibil_TESTFIXTURE0001')
        ->pan_masked->toBe('XXXXXX999Z')
        ->mobile_masked->toBe('******0001')
        ->in_flight_key->toBeNull()
        ->requested_by->toBe($this->officer->id)
        ->and($request->report_date->toDateString())->toBe('2026-10-16')
        ->and($request->raw_response)->toBe(cibilFixture())
        ->and($request->summary)->toMatchArray(['score' => 750, 'accounts_total' => '2', 'total_balance' => '45210']);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-cibil/fetch-report'));
});

it('stores the PAN, name, mobile, response and summary encrypted and out of serialisation', function () {
    fakeSurepassCibil();

    $request = requestCibil(cibilRequestFor($this->customer))->request;
    $row = (array) DB::table('cibil_credit_requests')->find($request->id);

    expect($row['pan_encrypted'])->not->toContain('AAAPZ9999Z')
        ->and($row['full_name'])->not->toContain('Test Fixture')
        ->and($row['mobile'])->not->toContain('9000000001')
        ->and($row['raw_response'])->not->toContain('FIXTURE BANK')
        ->and($row['summary'])->not->toContain('750')
        ->and($request->refresh()->toArray())->not->toHaveKeys(['pan_encrypted', 'full_name', 'mobile', 'raw_response', 'summary', 'pan_hash']);
});

it('fetches a score-only result from the score endpoint', function () {
    fakeSurepassCibil();

    $request = requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Score]))->request->refresh();

    expect($request)->product->toBe(CibilProduct::Score)->score->toBe(742)->status->toBe(CibilRequestStatus::Success)
        ->provider_client_id->toBe('cibil_credit_score_TESTFIXTURE0001')
        ->and($request->summary)->toBe(['score' => 742]);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-cibil/score'));
});

it('fetches a PDF, downloads it without the token into encrypted private storage, and reuses it', function () {
    Storage::fake(FetchCibilPdf::DISK);
    fakeSurepassCibil();

    $request = requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Pdf]))->request->refresh();

    expect($request)->product->toBe(CibilProduct::Pdf)->status->toBe(CibilRequestStatus::Success)->score->toBe(750)
        ->provider_client_id->toBe('credit_report_cibil_pdf_TESTFIXTURE0001')
        ->pdf_path->toBe('cibil-reports/'.$request->public_id.'.pdf.enc')
        ->pdf_error_code->toBeNull()
        ->and(Crypt::decryptString(Storage::disk(FetchCibilPdf::DISK)->get($request->pdf_path)))->toBe('%PDF-1.4 synthetic CIBIL test PDF')
        ->and(Storage::disk(FetchCibilPdf::DISK)->get($request->pdf_path))->not->toContain('%PDF');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://files.surepass.test/') && ! $request->hasHeader('Authorization'));

    $again = requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Pdf]));
    expect($again->kind)->toBe(CibilOutcome::REUSED)->and($again->request->is($request))->toBeTrue();
    Http::assertSentCount(2);
});

it('keeps a PDF request successful when its link cannot be downloaded, and does not reuse it', function () {
    Storage::fake(FetchCibilPdf::DISK);
    Http::fake(['files.surepass.test/*' => Http::response('expired', 403)]);
    fakeSurepassCibil();

    $request = requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Pdf]))->request->refresh();

    expect($request)->status->toBe(CibilRequestStatus::Success)->pdf_path->toBeNull()->pdf_error_code->toBe(CibilErrorCode::PdfUnavailable->value)
        ->and($request->isReusable())->toBeFalse()
        ->and(requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Pdf]))->kind)->toBe(CibilOutcome::QUEUED);
});

it('refuses without valid consent and sends nothing', function (Closure $consent, string $message) {
    fakeSurepassCibil();

    expect(fn () => requestCibil(cibilRequestFor($this->customer, ['consent' => $consent($this->customer)])))
        ->toThrow(CibilRequestRefused::class, $message);

    expect(CibilCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'not attested' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-1', now(), false), 'Confirm that the customer consented'],
    'no evidence' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::WrittenForm, '', now(), true), 'evidence reference'],
    'too old' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::Email, 'MAIL-1', now()->subDays(31), true), 'within the last 30 days'],
    'withdrawn' => [fn (Customer $customer) => CreditConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'status' => ConsentStatus::Withdrawn])), 'withdrawn'],
    'another customer' => [fn () => CreditConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => Customer::factory()->create()->id])), 'different customer'],
]);

it('rejects invalid details before anything is stored or sent', function (array $overrides, string $message) {
    fakeSurepassCibil();

    expect(fn () => requestCibil(cibilRequestFor($this->customer, $overrides)))->toThrow(CibilRequestRefused::class, $message);

    expect(CibilCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'bad PAN' => [['pan' => 'ABCD1234F'], 'valid PAN'],
    'no name' => [['name' => ' '], 'full name'],
    'bad mobile' => [['mobile' => '0123'], '10-digit'],
    'no gender' => [['gender' => null], 'gender'],
]);

it('refuses when switched off, without a token, or without the permission', function () {
    fakeSurepassCibil();
    Setting::set(CibilSettings::ENABLED, false);
    expect(fn () => requestCibil(cibilRequestFor($this->customer)))->toThrow(CibilRequestRefused::class, 'switched off');

    Setting::set(CibilSettings::ENABLED, true);
    config(['services.surepass.cibil_token' => '']);
    expect(fn () => requestCibil(cibilRequestFor($this->customer)))->toThrow(CibilRequestRefused::class, 'not configured');

    config(['services.surepass.cibil_token' => 'test-cibil-token']);
    expect(fn () => requestCibil(cibilRequestFor($this->customer), User::factory()->create()))->toThrow(AuthorizationException::class);
    Http::assertNothingSent();
});

it('blocks an equivalent request while one is in flight: any product blocks a score, a report or PDF only its own kind', function () {
    fakeSurepassCibil();
    $inFlightReport = CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->processing()->create();

    expect(requestCibil(cibilRequestFor($this->customer))->kind)->toBe(CibilOutcome::BLOCKED)
        ->and(requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Score]))->kind)->toBe(CibilOutcome::BLOCKED)
        ->and($inFlightReport->refresh()->duplicates_blocked)->toBe(2);
    Http::assertNothingSent();

    $inFlightReport->forceFill(['status' => CibilRequestStatus::Failed, 'in_flight_key' => null, 'retryable' => true])->save();
    CibilCreditRequest::factory()->pdf()->forPan('AAAPZ9999Z')->processing()->create();

    expect(requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Pdf]))->kind)->toBe(CibilOutcome::BLOCKED)
        ->and(requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Score]))->kind)->toBe(CibilOutcome::BLOCKED)
        ->and(requestCibil(cibilRequestFor($this->customer))->kind)->toBe(CibilOutcome::QUEUED);
});

it('reuses results by product: a report answers a report or a score, a score never answers a report', function () {
    fakeSurepassCibil();
    $score = CibilCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->successful()->create();

    expect(requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Score]))->request->is($score))->toBeTrue();
    expect(requestCibil(cibilRequestFor($this->customer))->kind)->toBe(CibilOutcome::QUEUED);

    $reused = requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Score]));
    expect($reused->kind)->toBe(CibilOutcome::REUSED)
        ->and($reused->request->product)->toBe(CibilProduct::Report)
        ->and(requestCibil(cibilRequestFor($this->customer))->kind)->toBe(CibilOutcome::REUSED);

    Http::assertSentCount(1);
});

it('applies separate reuse periods to scores and to reports', function () {
    fakeSurepassCibil();
    Setting::set(CibilSettings::SCORE_REUSE_DAYS, 7);
    Setting::set(CibilSettings::REPORT_REUSE_DAYS, 60);
    CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create(['completed_at' => now()->subDays(10)]);

    expect(requestCibil(cibilRequestFor($this->customer))->kind)->toBe(CibilOutcome::REUSED)
        ->and(requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Score]))->kind)->toBe(CibilOutcome::QUEUED);
});

it('keeps CIBIL separate: never reuses a CRIF, Equifax or Experian result, and they never see CIBIL rows', function () {
    fakeSurepassCibil();
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create();
    EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create();
    ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create();

    $request = requestCibil(cibilRequestFor($this->customer))->request;

    expect($request->refresh()->status)->toBe(CibilRequestStatus::Success)
        ->and(CibilCreditRequest::query()->count())->toBe(1)
        ->and(EquifaxCreditRequest::query()->count())->toBe(1)
        ->and(ExperianCreditRequest::query()->count())->toBe(1)
        ->and(CrifReport::query()->count())->toBe(1)
        ->and($this->customer->equifaxRequests()->count())->toBe(0)
        ->and($this->customer->experianRequests()->count())->toBe(0)
        ->and($this->customer->cibilRequests()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('lets an authorised user pull a fresh report with a recorded reason, and refuses others', function () {
    fakeSurepassCibil();
    CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create();
    $fresh = ['type' => CibilRequestType::Fresh, 'freshReason' => 'Lender requires a report dated this week'];

    expect(fn () => requestCibil(cibilRequestFor($this->customer, $fresh)))->toThrow(AuthorizationException::class);

    $manager = User::factory()->create();
    $manager->givePermissionTo('FetchFresh:CibilCreditRequest');
    expect(fn () => requestCibil(cibilRequestFor($this->customer, [...$fresh, 'freshReason' => 'needed']), $manager))->toThrow(CibilRequestRefused::class, '15 characters');

    $outcome = requestCibil(cibilRequestFor($this->customer, $fresh), $manager);
    expect($outcome->kind)->toBe(CibilOutcome::QUEUED)
        ->and($outcome->request->refresh())->request_type->toBe(CibilRequestType::Fresh)->fresh_reason->toBe('Lender requires a report dated this week')
        ->and(CibilCreditRequest::query()->count())->toBe(2);
});

it('refuses to resend details Surepass rejected, but accepts corrected ones', function () {
    fakeSurepassCibil();
    CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->failed(CibilErrorCode::NoRecordFound)->create([
        'request_fingerprint' => CibilFingerprint::request('AAAPZ9999Z', 'Test Fixture', '9000000001', 'male'),
    ]);

    expect(fn () => requestCibil(cibilRequestFor($this->customer)))->toThrow(CibilRequestRefused::class, 'No CIBIL record found');
    expect(requestCibil(cibilRequestFor($this->customer, ['name' => 'Test Kumar Fixture']))->kind)->toBe(CibilOutcome::QUEUED);
});

it('retries an unprocessed failure once and refuses to retry one that may have been charged', function () {
    fakeSurepassCibil();
    $consentId = CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $this->customer->id])->id;
    $failed = CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->failed(CibilErrorCode::ApiNotEnabled)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => $consentId]);
    $uncertain = CibilCreditRequest::factory()->forPan('BBBPZ1111Z')->failed(CibilErrorCode::Timeout)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => $consentId]);

    $retry = requestCibil(CibilCheckRequest::retryOf($failed))->request->refresh();
    expect($retry)->request_type->toBe(CibilRequestType::Retry)->previous_request_id->toBe($failed->id)->status->toBe(CibilRequestStatus::Success);

    expect(requestCibil(CibilCheckRequest::retryOf($failed))->kind)->toBe(CibilOutcome::REUSED)
        ->and(fn () => requestCibil(CibilCheckRequest::retryOf($uncertain)))->toThrow(CibilRequestRefused::class, 'known not to have processed');
    Http::assertSentCount(1);
});

it('lets the database hold only one unresolved request per PAN and product', function () {
    CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->create();

    expect(fn () => CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->processing()->create())
        ->toThrow(UniqueConstraintViolationException::class);

    CibilCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->create();
    CibilCreditRequest::factory()->pdf()->forPan('AAAPZ9999Z')->create();
    expect(CibilCreditRequest::query()->forPan(PanNumber::hash('AAAPZ9999Z'))->inFlight()->count())->toBe(3);
});

it('answers a request the in-flight check missed as blocked, because the unique index refuses it', function () {
    fakeSurepassCibil();
    // A competing row that holds the PAN's report key but that the status-based
    // in-flight query does not see — what a simultaneous insert looks like.
    $winner = CibilCreditRequest::factory()->forPan('AAAPZ9999Z')->create([
        'status' => CibilRequestStatus::Failed,
        'request_fingerprint' => str_repeat('0', 64),
    ]);

    $outcome = requestCibil(cibilRequestFor($this->customer));

    expect($outcome->kind)->toBe(CibilOutcome::BLOCKED)
        ->and($outcome->request->is($winner))->toBeTrue()
        ->and(CibilCreditRequest::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('refuses a request that arrives while another for the same PAN is being started', function () {
    fakeSurepassCibil();
    Sleep::fake(syncWithCarbon: true);
    $lock = Cache::lock('cibil-request:'.PanNumber::hash('AAAPZ9999Z'), 30);
    $lock->get();

    expect(fn () => requestCibil(cibilRequestFor($this->customer)))->toThrow(CibilRequestRefused::class, 'being started right now');

    Http::assertNothingSent();
    $lock->release();
});

it('gives other modules a read-only view that never calls Surepass', function () {
    fakeSurepassCibil();
    $service = app(CibilCreditService::class);
    expect($service->latestScore($this->customer))->toBeNull()->and($service->summary($this->customer))->toBeNull();

    requestCibil(cibilRequestFor($this->customer));

    expect($service->latestScore($this->customer))->toMatchArray(['score' => 750, 'product' => 'report', 'reusable' => true])
        ->and($service->summary($this->customer))->toMatchArray(['accounts_total' => '2', 'reusable' => true])
        ->and($service->latest($this->customer, CibilProduct::Pdf))->toBeNull();
    Http::assertSentCount(1);
});

it('keeps the PAN, mobile, name, score, report and PDF link out of logs and the audit trail', function () {
    Storage::fake(FetchCibilPdf::DISK);
    fakeSurepassCibil();
    Log::spy();

    $report = requestCibil(cibilRequestFor($this->customer))->request;
    $pdf = requestCibil(cibilRequestFor($this->customer, ['product' => CibilProduct::Pdf]))->request;
    $audit = json_encode([$report->auditLogs()->pluck('changes'), $pdf->auditLogs()->pluck('changes')]);

    expect($audit)->not->toContain('AAAPZ9999Z')->not->toContain('9000000001')->not->toContain('Test Fixture')->not->toContain('FIXTURE BANK')
        ->not->toContain('"score"')->not->toContain('files.surepass.test');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), 'AAAPZ9999Z')
        && ! str_contains(json_encode($context), '9000000001')
        && ! str_contains(json_encode($context), 'files.surepass.test')
        && ! str_contains(json_encode($context), 'test-cibil-token'))->atLeast()->once();
});

it('links a request to the application it came from', function () {
    fakeSurepassCibil();
    $session = JourneySession::factory()->create(['customer_id' => $this->customer->id]);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    $request = requestCibil(cibilRequestFor($this->customer, ['consent' => CreditConsentInput::existing($consent), 'journeySession' => $session]))->request;

    expect($request)->journey_session_id->toBe($session->id)->credit_consent_id->toBe($consent->id)
        ->and($session->cibilRequests()->count())->toBe(1);
});
