<?php

use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Actions\RequestCrifReport;
use App\Modules\CreditBureau\DataTransferObjects\CrifConsentInput;
use App\Modules\CreditBureau\DataTransferObjects\CrifReportRequest;
use App\Modules\CreditBureau\DataTransferObjects\CrifRequestOutcome;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRequestFingerprint;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

beforeEach(function () {
    CrifRoles::grant();
    $this->officer = User::factory()->create()->assignRole(CrifRoles::OFFICER_ROLE);
    $this->customer = Customer::factory()->create();
});

function requestCrif(CrifReportRequest $request, ?User $actor = null): CrifRequestOutcome
{
    return app(RequestCrifReport::class)->handle($request, $actor ?? test()->officer);
}

it('fetches, stores and indexes a report for a valid request', function () {
    fakeSurepassCrif();

    $outcome = requestCrif(crifRequestFor($this->customer, ['pan' => ' aaapz 9999z ']));

    $report = $outcome->report->refresh();
    expect($outcome->kind)->toBe(CrifRequestOutcome::QUEUED)
        ->and($report->status)->toBe(CrifReportStatus::Success)
        ->and($report->customer_id)->toBe($this->customer->id)
        ->and($report->requested_by)->toBe($this->officer->id)
        ->and($report->score)->toBe(742)
        ->and($report->provider_report_id)->toBe('FIXTURE-REPORT-0001')
        ->and($report->provider_inquiry_id)->toBe('FIXTURE-INQ-77')
        ->and($report->provider_client_id)->toBe('credit_report_crif_TESTFIXTURE0001')
        ->and($report->report_issued_on->toDateString())->toBe('2026-10-15')
        ->and($report->raw_response)->toBe(crifReportFixture())
        ->and($report->pan_encrypted)->toBe('AAAPZ9999Z')
        ->and($report->pan_hash)->toBe(PanNumber::hash('AAAPZ9999Z'))
        ->and($report->pan_masked)->toBe('XXXXXX999Z')
        ->and($report->mobile_masked)->toBe('******0001')
        ->and($report->in_flight_key)->toBeNull();
});

it('sends a middle name inside first_name and keeps it as entered', function () {
    fakeSurepassCrif();

    $report = requestCrif(crifRequestFor($this->customer, ['firstName' => 'Surya', 'middleName' => ' Prakash ', 'lastName' => 'Chamoli']))->report->refresh();

    Http::assertSent(fn ($request) => $request['first_name'] === 'Surya Prakash' && $request['last_name'] === 'Chamoli');
    expect($report)
        ->first_name->toBe('Surya')
        ->middle_name->toBe('Prakash')
        ->last_name->toBe('Chamoli')
        ->and($report->customerName())->toBe('Surya Prakash Chamoli')
        ->and((array) DB::table('crif_reports')->find($report->id))->not->toContain('Prakash');
});

it('stores the PAN, names, mobile and response encrypted at rest', function () {
    fakeSurepassCrif();

    $report = requestCrif(crifRequestFor($this->customer))->report;
    $row = (array) DB::table('crif_reports')->find($report->id);

    expect($row['pan_encrypted'])->not->toContain('AAAPZ9999Z')
        ->and($row['first_name'])->not->toBe('Test')
        ->and($row['mobile'])->not->toContain('9000000001')
        ->and($row['raw_response'])->not->toContain('FIXTURE-REPORT-0001')
        ->and($report->refresh()->toArray())->not->toHaveKeys(['pan_encrypted', 'first_name', 'last_name', 'mobile', 'raw_response', 'pan_hash']);
});

it('records the employee consent with its evidence before sending', function () {
    fakeSurepassCrif();

    $report = requestCrif(crifRequestFor($this->customer))->report;

    expect($report->consent)
        ->customer_id->toBe($this->customer->id)
        ->method->toBe(ConsentMethod::RecordedCall)
        ->evidence_reference->toBe('CALL-REF-001')
        ->recorded_by->toBe($this->officer->id)
        ->status->toBe(ConsentStatus::Given);
});

it('accepts a recent website-journey consent for the same customer and PAN', function () {
    fakeSurepassCrif();
    $session = JourneySession::factory()->create(['customer_id' => $this->customer->id]);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    $report = requestCrif(crifRequestFor($this->customer, [
        'consent' => CrifConsentInput::existing($consent),
        'journeySession' => $session,
    ]))->report;

    expect($report->credit_consent_id)->toBe($consent->id)
        ->and($report->journey_session_id)->toBe($session->id)
        ->and(CreditConsent::query()->count())->toBe(1);
});

it('refuses without valid consent and sends nothing', function (Closure $consent, string $message) {
    fakeSurepassCrif();

    expect(fn () => requestCrif(crifRequestFor($this->customer, ['consent' => $consent($this->customer)])))
        ->toThrow(CrifRequestRefused::class, $message);

    expect(CrifReport::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'not attested' => [fn () => CrifConsentInput::recordedByEmployee(ConsentMethod::RecordedCall, 'CALL-1', now(), false), 'Confirm that the customer consented'],
    'no evidence' => [fn () => CrifConsentInput::recordedByEmployee(ConsentMethod::WrittenForm, ' ', now(), true), 'evidence reference'],
    'consent too old' => [fn () => CrifConsentInput::recordedByEmployee(ConsentMethod::Email, 'MAIL-1', now()->subDays(31), true), 'within the last 30 days'],
    'consent in the future' => [fn () => CrifConsentInput::recordedByEmployee(ConsentMethod::Email, 'MAIL-1', now()->addDay(), true), 'not in the future'],
    'journey method typed by hand' => [fn () => CrifConsentInput::recordedByEmployee(ConsentMethod::OnlineJourney, 'X-1', now(), true), 'Choose how the customer gave consent'],
    'withdrawn consent' => [fn (Customer $customer) => CrifConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'status' => ConsentStatus::Withdrawn])), 'withdrawn'],
    'another customer\'s consent' => [fn () => CrifConsentInput::existing(CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => Customer::factory()->create()->id])), 'different customer'],
    'expired journey consent' => [fn (Customer $customer) => CrifConsentInput::existing(CreditConsent::factory()->create([
        'journey_session_id' => JourneySession::factory()->create(['customer_id' => $customer->id])->id,
        'consented_at' => now()->subDays(45),
    ])), 'older than 30 days'],
    'consent for another PAN' => [fn (Customer $customer) => CrifConsentInput::existing(CreditConsent::factory()->create([
        'journey_session_id' => JourneySession::factory()->create(['customer_id' => $customer->id])->id,
        'pan_number' => 'BBBPZ1111Z',
    ])), 'different PAN'],
]);

it('rejects invalid customer details before anything is stored or sent', function (array $overrides, string $message) {
    fakeSurepassCrif();

    expect(fn () => requestCrif(crifRequestFor($this->customer, $overrides)))->toThrow(CrifRequestRefused::class, $message);

    expect(CrifReport::query()->count())->toBe(0)
        ->and(CreditConsent::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'malformed PAN' => [['pan' => 'ABCD1234F'], 'valid PAN'],
    'digits where letters belong' => [['pan' => '12345ABCDE'], 'valid PAN'],
    'missing last name' => [['lastName' => '  '], 'first and last name'],
    'invalid mobile' => [['mobile' => '12345'], '10-digit Indian mobile'],
]);

it('refuses while the integration is switched off or has no token', function (Closure $disable, string $message) {
    fakeSurepassCrif();
    $disable();

    expect(fn () => requestCrif(crifRequestFor($this->customer)))->toThrow(CrifRequestRefused::class, $message);
    Http::assertNothingSent();
})->with([
    'switched off' => [fn () => Setting::set(CrifSettings::ENABLED, false), 'switched off'],
    'no token' => [fn () => config(['services.surepass.token' => '']), 'not configured'],
]);

it('forbids employees without the create permission', function () {
    fakeSurepassCrif();

    expect(fn () => requestCrif(crifRequestFor($this->customer), User::factory()->create()))
        ->toThrow(AuthorizationException::class);
    Http::assertNothingSent();
});

it('blocks a second request while one for the same PAN is in flight', function (CrifReportStatus $status) {
    fakeSurepassCrif();
    $inFlight = CrifReport::factory()->forPan('AAAPZ9999Z')->create(['status' => $status]);

    $outcome = requestCrif(crifRequestFor($this->customer, ['pan' => 'aaapz9999z']));

    expect($outcome->kind)->toBe(CrifRequestOutcome::BLOCKED)
        ->and($outcome->report->is($inFlight))->toBeTrue()
        ->and($inFlight->refresh()->duplicates_blocked)->toBe(1)
        ->and(CrifReport::query()->count())->toBe(1)
        ->and($inFlight->auditLogs()->where('action', 'duplicate_blocked')->exists())->toBeTrue();
    Http::assertNothingSent();
})->with([
    'pending' => [CrifReportStatus::Pending],
    'processing' => [CrifReportStatus::Processing],
    'awaiting reconciliation' => [CrifReportStatus::ReconciliationRequired],
]);

it('blocks even a fresh override while a request is in flight', function () {
    fakeSurepassCrif();
    CrifReport::factory()->forPan('AAAPZ9999Z')->processing()->create();

    $outcome = requestCrif(crifRequestFor($this->customer, ['type' => CrifRequestType::Fresh, 'freshReason' => 'Lender requires a report dated today']), User::factory()->create(['is_admin' => true]));

    expect($outcome->kind)->toBe(CrifRequestOutcome::BLOCKED);
    Http::assertNothingSent();
});

it('reuses a successful report inside the reuse period instead of paying again', function () {
    fakeSurepassCrif();
    $existing = CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create(['completed_at' => now()->subDays(29)]);

    $outcome = requestCrif(crifRequestFor($this->customer));

    expect($outcome->kind)->toBe(CrifRequestOutcome::REUSED)
        ->and($outcome->report->is($existing))->toBeTrue()
        ->and($existing->refresh()->times_reused)->toBe(1)
        ->and(CrifReport::query()->count())->toBe(1)
        ->and($existing->auditLogs()->where('action', 'reused')->value('user_id'))->toBe($this->officer->id);
    Http::assertNothingSent();
});

it('fetches a new report once the reuse period has passed', function () {
    fakeSurepassCrif();
    Setting::set(CrifSettings::REUSE_DAYS, 7);
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create(['completed_at' => now()->subDays(8)]);

    $outcome = requestCrif(crifRequestFor($this->customer));

    expect($outcome->kind)->toBe(CrifRequestOutcome::QUEUED)
        ->and(CrifReport::query()->count())->toBe(2);
    Http::assertSentCount(1);
});

it('never reuses a report from another bureau check or an expired one', function () {
    fakeSurepassCrif();
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create(['status' => CrifReportStatus::Expired, 'purged_at' => now()]);

    expect(requestCrif(crifRequestFor($this->customer))->kind)->toBe(CrifRequestOutcome::QUEUED);
});

it('lets the database hold only one unresolved request per PAN', function () {
    CrifReport::factory()->forPan('AAAPZ9999Z')->create();

    expect(fn () => CrifReport::factory()->forPan('AAAPZ9999Z')->processing()->create())
        ->toThrow(UniqueConstraintViolationException::class);

    // Settled attempts release the key, so a PAN's history can hold any number of them.
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->count(2)->create();
    expect(CrifReport::query()->forPan(PanNumber::hash('AAAPZ9999Z'))->count())->toBe(3);
});

it('refuses a request that arrives while another for the same PAN is being started', function () {
    fakeSurepassCrif();
    Sleep::fake(syncWithCarbon: true);
    $lock = Cache::lock('crif-request:'.PanNumber::hash('AAAPZ9999Z'), 30);
    $lock->get();

    expect(fn () => requestCrif(crifRequestFor($this->customer)))
        ->toThrow(CrifRequestRefused::class, 'being started right now');

    expect(CrifReport::query()->count())->toBe(0);
    Http::assertNothingSent();
    $lock->release();
});

it('lets an authorised user fetch a fresh report over a reusable one, with the reason recorded', function () {
    fakeSurepassCrif();
    $existing = CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create();
    $manager = User::factory()->create();
    $manager->givePermissionTo('FetchFresh:CrifReport');

    $outcome = requestCrif(crifRequestFor($this->customer, [
        'type' => CrifRequestType::Fresh,
        'freshReason' => 'Lender requires a report dated this week',
        'previousReport' => $existing,
    ]), $manager);

    expect($outcome->kind)->toBe(CrifRequestOutcome::QUEUED)
        ->and($outcome->report->refresh())
        ->request_type->toBe(CrifRequestType::Fresh)
        ->fresh_reason->toBe('Lender requires a report dated this week')
        ->status->toBe(CrifReportStatus::Success)
        ->and($outcome->report->auditLogs()->where('action', 'created')->value('changes'))
        ->toHaveKey('fresh_reason');
    Http::assertSentCount(1);
});

it('refuses a fresh report without the permission or without a real reason', function (bool $permitted, string $reason, string $exception) {
    fakeSurepassCrif();
    CrifReport::factory()->forPan('AAAPZ9999Z')->successful()->create();
    $user = User::factory()->create()->assignRole(CrifRoles::OFFICER_ROLE);

    if ($permitted) {
        $user->givePermissionTo('FetchFresh:CrifReport');
    }

    expect(fn () => requestCrif(crifRequestFor($this->customer, ['type' => CrifRequestType::Fresh, 'freshReason' => $reason]), $user))
        ->toThrow($exception);
    Http::assertNothingSent();
})->with([
    'credit officer' => [false, 'Lender requires a report dated today', AuthorizationException::class],
    'reason too short' => [true, 'needed', CrifRequestRefused::class],
]);

it('refuses to resend details Surepass already rejected, but accepts corrected ones', function () {
    fakeSurepassCrif();
    CrifReport::factory()->forPan('AAAPZ9999Z')->failed(CrifErrorCode::NoRecordFound)->create([
        'first_name' => 'Test',
        'last_name' => 'Fixture',
        'mobile' => '9000000001',
        'request_fingerprint' => CrifRequestFingerprint::make('AAAPZ9999Z', 'Test', 'Fixture', '9000000001'),
    ]);

    expect(fn () => requestCrif(crifRequestFor($this->customer)))->toThrow(CrifRequestRefused::class, 'No CRIF record found');
    Http::assertNothingSent();

    expect(requestCrif(crifRequestFor($this->customer, ['firstName' => 'Testa']))->kind)->toBe(CrifRequestOutcome::QUEUED);
});

it('retries a failed attempt Surepass never processed, exactly once', function () {
    fakeSurepassCrif();
    $failed = CrifReport::factory()->forPan('AAAPZ9999Z')->failed(CrifErrorCode::ProviderUnavailable)->create(['customer_id' => $this->customer->id, 'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $this->customer->id])->id]);

    $retry = requestCrif(CrifReportRequest::retryOf($failed))->report->refresh();

    expect($retry->request_type)->toBe(CrifRequestType::Retry)
        ->and($retry->previous_report_id)->toBe($failed->id)
        ->and($retry->status)->toBe(CrifReportStatus::Success);

    // The retry succeeded, so a second retry of the same attempt is answered with that report.
    expect(requestCrif(CrifReportRequest::retryOf($failed))->kind)->toBe(CrifRequestOutcome::REUSED);
    Http::assertSentCount(1);
});

it('refuses to retry an attempt that may have been charged', function (Closure $state) {
    fakeSurepassCrif();
    $report = $state(CrifReport::factory()->forPan('AAAPZ9999Z'), $this->customer);

    expect(fn () => requestCrif(CrifReportRequest::retryOf($report)))->toThrow(CrifRequestRefused::class);
    Http::assertNothingSent();
})->with([
    'non-retryable failure' => [fn ($factory, $customer) => $factory->failed(CrifErrorCode::InvalidRequest)->create(['customer_id' => $customer->id, 'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id])->id])],
    'success' => [fn ($factory, $customer) => $factory->successful()->create(['customer_id' => $customer->id, 'completed_at' => now()->subYear(), 'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id])->id])],
]);

it('never logs the full PAN, mobile number or token', function () {
    fakeSurepassCrif();
    Log::spy();

    requestCrif(crifRequestFor($this->customer));

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        $logged = json_encode([$message, $context]);

        return ! str_contains($logged, 'AAAPZ9999Z') && ! str_contains($logged, '9000000001') && ! str_contains($logged, 'test-surepass-token');
    })->atLeast()->once();
    Log::shouldNotHaveReceived('info', [Mockery::on(fn ($message) => str_contains((string) $message, 'AAAPZ9999Z'))]);
});

it('keeps the PAN, names, score and response out of the audit trail', function () {
    fakeSurepassCrif();

    $report = requestCrif(crifRequestFor($this->customer))->report;
    $audit = json_encode($report->auditLogs()->pluck('changes'));

    expect($audit)->not->toContain('AAAPZ9999Z')
        ->not->toContain('9000000001')
        ->not->toContain('Fixture')
        ->not->toContain('FIXTURE BANK')
        ->not->toContain('"score"');
});
