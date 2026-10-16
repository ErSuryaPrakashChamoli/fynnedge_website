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
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Actions\FetchExperianPdf;
use App\Modules\Experian\Actions\RequestExperianCheck;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\DataTransferObjects\ExperianOutcome;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianCreditService;
use App\Modules\Experian\Services\ExperianFingerprint;
use App\Modules\Experian\Services\ExperianRoles;
use App\Modules\Experian\Services\ExperianSettings;
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
    ExperianRoles::grant();
    $this->officer = User::factory()->create()->assignRole(CrifRoles::OFFICER_ROLE);
    $this->customer = Customer::factory()->create();
});

function requestExperian(ExperianCheckRequest $request, ?User $actor = null): ExperianOutcome
{
    return app(RequestExperianCheck::class)->handle($request, $actor ?? test()->officer);
}

it('fetches, stores and indexes a full report, keeping the complete response and a separate summary', function () {
    fakeSurepassExperian();

    $outcome = requestExperian(experianRequestFor($this->customer, ['pan' => ' aaapz 9999z ']));
    $request = $outcome->request->refresh();

    expect($outcome->kind)->toBe(ExperianOutcome::QUEUED)
        ->and($request)
        ->status->toBe(ExperianRequestStatus::Success)
        ->product->toBe(ExperianProduct::Report)
        ->score->toBe(792)
        ->provider_report_id->toBe('1760600000000')
        ->provider_client_id->toBe('credit_report_experian_TESTFIXTURE0001')
        ->pan_masked->toBe('XXXXXX999Z')
        ->mobile_masked->toBe('******0001')
        ->in_flight_key->toBeNull()
        ->requested_by->toBe($this->officer->id)
        ->and($request->report_date->toDateString())->toBe('2026-10-16')
        ->and($request->raw_response)->toBe(experianFixture())
        ->and($request->summary)->toMatchArray(['score' => 792, 'accounts_total' => '2', 'total_outstanding' => '45210']);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-experian/fetch-report'));
});

it('stores the PAN, name, mobile, response and summary encrypted and out of serialisation', function () {
    fakeSurepassExperian();

    $request = requestExperian(experianRequestFor($this->customer))->request;
    $row = (array) DB::table('experian_credit_requests')->find($request->id);

    expect($row['pan_encrypted'])->not->toContain('AAAPZ9999Z')
        ->and($row['full_name'])->not->toContain('Test Fixture')
        ->and($row['mobile'])->not->toContain('9000000001')
        ->and($row['raw_response'])->not->toContain('Fixture Bank')
        ->and($row['summary'])->not->toContain('792')
        ->and($request->refresh()->toArray())->not->toHaveKeys(['pan_encrypted', 'full_name', 'mobile', 'raw_response', 'summary', 'pan_hash']);
});

it('fetches a score-only result from the score endpoint', function () {
    fakeSurepassExperian();

    $request = requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Score]))->request->refresh();

    expect($request)->product->toBe(ExperianProduct::Score)->score->toBe(796)->status->toBe(ExperianRequestStatus::Success)
        ->provider_client_id->toBe('experian_credit_score_TESTFIXTURE0001')
        ->and($request->summary)->toBe(['score' => 796]);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-experian/score'));
});

it('fetches a PDF, downloads it without the token into encrypted private storage, and reuses it', function () {
    Storage::fake(FetchExperianPdf::DISK);
    fakeSurepassExperian();

    $request = requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Pdf]))->request->refresh();

    expect($request)->product->toBe(ExperianProduct::Pdf)->status->toBe(ExperianRequestStatus::Success)->score->toBe(761)
        ->provider_client_id->toBe('credit_report_experian_pdf_TESTFIXTURE0001')
        ->pdf_path->toBe('experian-reports/'.$request->public_id.'.pdf.enc')
        ->pdf_error_code->toBeNull()
        ->and(Crypt::decryptString(Storage::disk(FetchExperianPdf::DISK)->get($request->pdf_path)))->toBe('%PDF-1.4 synthetic test PDF')
        ->and(Storage::disk(FetchExperianPdf::DISK)->get($request->pdf_path))->not->toContain('%PDF');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://files.surepass.test/') && ! $request->hasHeader('Authorization'));

    $again = requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Pdf]));
    expect($again->kind)->toBe(ExperianOutcome::REUSED)->and($again->request->is($request))->toBeTrue();
    Http::assertSentCount(2);
});

it('keeps a PDF request successful when its link cannot be downloaded, and does not reuse it', function () {
    Storage::fake(FetchExperianPdf::DISK);
    Http::fake(['files.surepass.test/*' => Http::response('expired', 403)]);
    fakeSurepassExperian();

    $request = requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Pdf]))->request->refresh();

    expect($request)->status->toBe(ExperianRequestStatus::Success)->pdf_path->toBeNull()->pdf_error_code->toBe(ExperianErrorCode::PdfUnavailable->value)
        ->and($request->isReusable())->toBeFalse()
        ->and(requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Pdf]))->kind)->toBe(ExperianOutcome::QUEUED);
});

it('refuses without valid consent and sends nothing', function (Closure $consent, string $message) {
    fakeSurepassExperian();

    expect(fn () => requestExperian(experianRequestFor($this->customer, ['consent' => $consent($this->customer)])))
        ->toThrow(ExperianRequestRefused::class, $message);

    expect(ExperianCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'not attested' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-1', now(), false), 'Confirm that the customer consented'],
    'no evidence' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::WrittenForm, '', now(), true), 'evidence reference'],
    'too old' => [fn () => CreditConsentInput::recordedByEmployee(ConsentMethod::Email, 'MAIL-1', now()->subDays(31), true), 'within the last 30 days'],
    'withdrawn' => [fn (Customer $customer) => CreditConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'status' => ConsentStatus::Withdrawn])), 'withdrawn'],
    'another customer' => [fn () => CreditConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => Customer::factory()->create()->id])), 'different customer'],
]);

it('rejects invalid details before anything is stored or sent', function (array $overrides, string $message) {
    fakeSurepassExperian();

    expect(fn () => requestExperian(experianRequestFor($this->customer, $overrides)))->toThrow(ExperianRequestRefused::class, $message);

    expect(ExperianCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'bad PAN' => [['pan' => 'ABCD1234F'], 'valid PAN'],
    'no name' => [['name' => ' '], 'full name'],
    'bad mobile' => [['mobile' => '0123'], '10-digit'],
]);

it('refuses when switched off, without a token, or without the permission', function () {
    fakeSurepassExperian();
    Setting::set(ExperianSettings::ENABLED, false);
    expect(fn () => requestExperian(experianRequestFor($this->customer)))->toThrow(ExperianRequestRefused::class, 'switched off');

    Setting::set(ExperianSettings::ENABLED, true);
    config(['services.surepass.token' => '']);
    expect(fn () => requestExperian(experianRequestFor($this->customer)))->toThrow(ExperianRequestRefused::class, 'not configured');

    config(['services.surepass.token' => 'test-surepass-token']);
    expect(fn () => requestExperian(experianRequestFor($this->customer), User::factory()->create()))->toThrow(AuthorizationException::class);
    Http::assertNothingSent();
});

it('blocks an equivalent request while one is in flight: any product blocks a score, a report or PDF only its own kind', function () {
    fakeSurepassExperian();
    $inFlightReport = ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->processing()->create();

    expect(requestExperian(experianRequestFor($this->customer))->kind)->toBe(ExperianOutcome::BLOCKED)
        ->and(requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Score]))->kind)->toBe(ExperianOutcome::BLOCKED)
        ->and($inFlightReport->refresh()->duplicates_blocked)->toBe(2);
    Http::assertNothingSent();

    $inFlightReport->forceFill(['status' => ExperianRequestStatus::Failed, 'in_flight_key' => null, 'retryable' => true])->save();
    ExperianCreditRequest::factory()->pdf()->forPan('AAAPZ9999Z')->processing()->create();

    expect(requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Pdf]))->kind)->toBe(ExperianOutcome::BLOCKED)
        ->and(requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Score]))->kind)->toBe(ExperianOutcome::BLOCKED)
        ->and(requestExperian(experianRequestFor($this->customer))->kind)->toBe(ExperianOutcome::QUEUED);
});

it('reuses results by product: a report answers a report or a score, a score never answers a report', function () {
    fakeSurepassExperian();
    $score = ExperianCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->successful()->create();

    expect(requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Score]))->request->is($score))->toBeTrue();
    expect(requestExperian(experianRequestFor($this->customer))->kind)->toBe(ExperianOutcome::QUEUED);

    $reused = requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Score]));
    expect($reused->kind)->toBe(ExperianOutcome::REUSED)
        ->and($reused->request->product)->toBe(ExperianProduct::Report)
        ->and(requestExperian(experianRequestFor($this->customer))->kind)->toBe(ExperianOutcome::REUSED);

    Http::assertSentCount(1);
});

it('applies separate reuse periods to scores and to reports', function () {
    fakeSurepassExperian();
    Setting::set(ExperianSettings::SCORE_REUSE_DAYS, 7);
    Setting::set(ExperianSettings::REPORT_REUSE_DAYS, 60);
    ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create(['completed_at' => now()->subDays(10)]);

    expect(requestExperian(experianRequestFor($this->customer))->kind)->toBe(ExperianOutcome::REUSED)
        ->and(requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Score]))->kind)->toBe(ExperianOutcome::QUEUED);
});

it('keeps Experian separate: never reuses a CRIF or Equifax result, and they never see Experian rows', function () {
    fakeSurepassExperian();
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create();
    EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create();

    $request = requestExperian(experianRequestFor($this->customer))->request;

    expect($request->refresh()->status)->toBe(ExperianRequestStatus::Success)
        ->and(ExperianCreditRequest::query()->count())->toBe(1)
        ->and(EquifaxCreditRequest::query()->count())->toBe(1)
        ->and(CrifReport::query()->count())->toBe(1)
        ->and($this->customer->equifaxRequests()->count())->toBe(0)
        ->and($this->customer->experianRequests()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('lets an authorised user pull a fresh report with a recorded reason, and refuses others', function () {
    fakeSurepassExperian();
    ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->successful()->create();
    $fresh = ['type' => ExperianRequestType::Fresh, 'freshReason' => 'Lender requires a report dated this week'];

    expect(fn () => requestExperian(experianRequestFor($this->customer, $fresh)))->toThrow(AuthorizationException::class);

    $manager = User::factory()->create();
    $manager->givePermissionTo('FetchFresh:ExperianCreditRequest');
    expect(fn () => requestExperian(experianRequestFor($this->customer, [...$fresh, 'freshReason' => 'needed']), $manager))->toThrow(ExperianRequestRefused::class, '15 characters');

    $outcome = requestExperian(experianRequestFor($this->customer, $fresh), $manager);
    expect($outcome->kind)->toBe(ExperianOutcome::QUEUED)
        ->and($outcome->request->refresh())->request_type->toBe(ExperianRequestType::Fresh)->fresh_reason->toBe('Lender requires a report dated this week')
        ->and(ExperianCreditRequest::query()->count())->toBe(2);
});

it('refuses to resend details Surepass rejected, but accepts corrected ones', function () {
    fakeSurepassExperian();
    ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->failed(ExperianErrorCode::NoRecordFound)->create([
        'request_fingerprint' => ExperianFingerprint::request('AAAPZ9999Z', 'Test Fixture', '9000000001'),
    ]);

    expect(fn () => requestExperian(experianRequestFor($this->customer)))->toThrow(ExperianRequestRefused::class, 'No Experian record found');
    expect(requestExperian(experianRequestFor($this->customer, ['name' => 'Test Kumar Fixture']))->kind)->toBe(ExperianOutcome::QUEUED);
});

it('retries an unprocessed failure once and refuses to retry one that may have been charged', function () {
    fakeSurepassExperian();
    $consentId = CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $this->customer->id])->id;
    $failed = ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->failed(ExperianErrorCode::ApiNotEnabled)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => $consentId]);
    $uncertain = ExperianCreditRequest::factory()->forPan('BBBPZ1111Z')->failed(ExperianErrorCode::Timeout)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => $consentId]);

    $retry = requestExperian(ExperianCheckRequest::retryOf($failed))->request->refresh();
    expect($retry)->request_type->toBe(ExperianRequestType::Retry)->previous_request_id->toBe($failed->id)->status->toBe(ExperianRequestStatus::Success);

    expect(requestExperian(ExperianCheckRequest::retryOf($failed))->kind)->toBe(ExperianOutcome::REUSED)
        ->and(fn () => requestExperian(ExperianCheckRequest::retryOf($uncertain)))->toThrow(ExperianRequestRefused::class, 'known not to have processed');
    Http::assertSentCount(1);
});

it('lets the database hold only one unresolved request per PAN and product', function () {
    ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->create();

    expect(fn () => ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->processing()->create())
        ->toThrow(UniqueConstraintViolationException::class);

    ExperianCreditRequest::factory()->score()->forPan('AAAPZ9999Z')->create();
    ExperianCreditRequest::factory()->pdf()->forPan('AAAPZ9999Z')->create();
    expect(ExperianCreditRequest::query()->forPan(PanNumber::hash('AAAPZ9999Z'))->inFlight()->count())->toBe(3);
});

it('answers a request the in-flight check missed as blocked, because the unique index refuses it', function () {
    fakeSurepassExperian();
    // A competing row that holds the PAN's report key but that the status-based
    // in-flight query does not see — what a simultaneous insert looks like.
    $winner = ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->create([
        'status' => ExperianRequestStatus::Failed,
        'request_fingerprint' => str_repeat('0', 64),
    ]);

    $outcome = requestExperian(experianRequestFor($this->customer));

    expect($outcome->kind)->toBe(ExperianOutcome::BLOCKED)
        ->and($outcome->request->is($winner))->toBeTrue()
        ->and(ExperianCreditRequest::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('refuses a request that arrives while another for the same PAN is being started', function () {
    fakeSurepassExperian();
    Sleep::fake(syncWithCarbon: true);
    $lock = Cache::lock('experian-request:'.PanNumber::hash('AAAPZ9999Z'), 30);
    $lock->get();

    expect(fn () => requestExperian(experianRequestFor($this->customer)))->toThrow(ExperianRequestRefused::class, 'being started right now');

    Http::assertNothingSent();
    $lock->release();
});

it('gives other modules a read-only view that never calls Surepass', function () {
    fakeSurepassExperian();
    $service = app(ExperianCreditService::class);
    expect($service->latestScore($this->customer))->toBeNull()->and($service->summary($this->customer))->toBeNull();

    requestExperian(experianRequestFor($this->customer));

    expect($service->latestScore($this->customer))->toMatchArray(['score' => 792, 'product' => 'report', 'reusable' => true])
        ->and($service->summary($this->customer))->toMatchArray(['accounts_total' => '2', 'reusable' => true])
        ->and($service->latest($this->customer, ExperianProduct::Pdf))->toBeNull();
    Http::assertSentCount(1);
});

it('keeps the PAN, mobile, name, score, report and PDF link out of logs and the audit trail', function () {
    Storage::fake(FetchExperianPdf::DISK);
    fakeSurepassExperian();
    Log::spy();

    $report = requestExperian(experianRequestFor($this->customer))->request;
    $pdf = requestExperian(experianRequestFor($this->customer, ['product' => ExperianProduct::Pdf]))->request;
    $audit = json_encode([$report->auditLogs()->pluck('changes'), $pdf->auditLogs()->pluck('changes')]);

    expect($audit)->not->toContain('AAAPZ9999Z')->not->toContain('9000000001')->not->toContain('Test Fixture')->not->toContain('Fixture Bank')
        ->not->toContain('"score"')->not->toContain('files.surepass.test');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), 'AAAPZ9999Z')
        && ! str_contains(json_encode($context), '9000000001')
        && ! str_contains(json_encode($context), 'files.surepass.test')
        && ! str_contains(json_encode($context), 'test-surepass-token'))->atLeast()->once();
});

it('links a request to the application it came from', function () {
    fakeSurepassExperian();
    $session = JourneySession::factory()->create(['customer_id' => $this->customer->id]);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    $request = requestExperian(experianRequestFor($this->customer, ['consent' => CreditConsentInput::existing($consent), 'journeySession' => $session]))->request;

    expect($request)->journey_session_id->toBe($session->id)->credit_consent_id->toBe($consent->id)
        ->and($session->experianRequests()->count())->toBe(1);
});
