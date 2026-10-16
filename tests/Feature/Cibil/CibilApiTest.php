<?php

use App\Models\User;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilFingerprint;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    CrifRoles::grant();
    CibilRoles::grant();
    $this->officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $this->customer = Customer::factory()->create(['full_name' => 'Test Fixture', 'phone' => '9000000001']);
});

function cibilApiPayload(Customer $customer, array $overrides = []): array
{
    return [
        'customer_id' => $customer->public_id,
        'pan' => 'aaapz9999z',
        'gender' => 'Female',
        'consent' => [
            'method' => 'recorded_call',
            'evidence_reference' => 'CALL-API-001',
            'consented_at' => now()->subHour()->toIso8601String(),
            'attested' => true,
        ],
        ...$overrides,
    ];
}

function fixtureCibilRequest(Customer $customer, array $attributes = [], ?Closure $state = null): CibilCreditRequest
{
    $factory = CibilCreditRequest::factory();

    return ($state ? $state($factory) : $factory)->forPan('AAAPZ9999Z')->successful(cibilFixture(), 750)->create([
        'customer_id' => $customer->id,
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'pan_number' => 'AAAPZ9999Z'])->id,
        'mobile' => '9000000001',
        'mobile_masked' => '******0001',
        ...$attributes,
    ]);
}

function cibilUserWith(array $permissions): User
{
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('answers a signed-out caller with 401 JSON, never a redirect', function (string $method, string $path) {
    $this->json($method, $path)->assertUnauthorized();
    $this->{$method === 'GET' ? 'get' : 'post'}($path)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
})->with([
    ['POST', '/api/v1/cibil/reports'],
    ['POST', '/api/v1/cibil/score'],
    ['GET', '/api/v1/cibil/requests/'.Str::uuid().'/status'],
    ['GET', '/api/v1/cibil/customers/'.Str::uuid().'/reports'],
]);

it('requests a full report, defaulting name and mobile to the customer, and returns 202 with a masked request', function () {
    fakeSurepassCibil();

    $response = $this->actingAs($this->officer)->postJson('/api/v1/cibil/reports', cibilApiPayload($this->customer));

    $response->assertStatus(202)
        ->assertJson(['success' => true, 'outcome' => 'queued', 'data' => ['product' => 'report', 'pan_masked' => 'XXXXXX999Z', 'mobile_masked' => '******0001', 'customer_id' => $this->customer->public_id]])
        ->assertJsonMissingPath('data.raw')
        ->assertJsonMissingPath('data.report');

    expect($response->getContent())->not->toContain('AAAPZ9999Z')->not->toContain('9000000001')->not->toContain('test-surepass-token')
        ->and(CibilCreditRequest::query()->sole())->full_name->toBe('Test Fixture')->channel->toBe('api');
    Http::assertSent(fn (Request $request) => $request->data() === ['mobile' => '9000000001', 'pan' => 'AAAPZ9999Z', 'name' => 'Test Fixture', 'gender' => 'female', 'consent' => 'Y']);
});

it('answers 200 for a reused result and 409 for an equivalent request in flight', function () {
    fakeSurepassCibil();
    $existing = fixtureCibilRequest($this->customer);

    $this->actingAs($this->officer)->postJson('/api/v1/cibil/score', cibilApiPayload($this->customer))
        ->assertOk()->assertJson(['outcome' => 'reused', 'data' => ['id' => (string) $existing->public_id, 'score' => 750]]);

    $existing->forceFill(['status' => 'processing', 'in_flight_key' => CibilFingerprint::inFlight($existing->pan_hash, 'report')])->save();

    $this->postJson('/api/v1/cibil/reports', cibilApiPayload($this->customer))
        ->assertStatus(409)->assertJson(['success' => false, 'outcome' => 'blocked']);
    Http::assertNothingSent();
});

it('validates input with the standard envelope, without echoing the PAN', function (array $overrides, string $field) {
    fakeSurepassCibil();

    $response = $this->actingAs($this->officer)->postJson('/api/v1/cibil/reports', cibilApiPayload($this->customer, $overrides))
        ->assertStatus(422)
        ->assertJson(['success' => false, 'error_code' => 'validation_failed'])
        ->assertJsonValidationErrors($field);

    expect($response->getContent())->not->toContain('ZZZZZ1234')->not->toContain('AAAPZ9999Z');
    Http::assertNothingSent();
})->with([
    'bad PAN' => [['pan' => 'ZZZZZ1234'], 'pan'],
    'bad mobile' => [['mobile' => '12345'], 'mobile'],
    'name too long' => [['name' => str_repeat('a', 151)], 'name'],
    'unknown customer' => [['customer_id' => (string) Str::uuid()], 'customer_id'],
    'consent missing' => [['consent' => ['attested' => true]], 'consent.method'],
    'consent not attested' => [['consent' => ['method' => 'otp', 'evidence_reference' => 'OTP-1', 'consented_at' => now()->toIso8601String(), 'attested' => false]], 'consent.attested'],
    'fresh without reason' => [['fresh' => true], 'fresh_reason'],
    'missing gender' => [['gender' => null], 'gender'],
    'unsupported gender' => [['gender' => 'other'], 'gender'],
]);

it('refuses a request from staff without CIBIL permission (403) and for another customer\'s application (422)', function () {
    fakeSurepassCibil();
    $marketing = tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']);

    $this->actingAs($marketing)->postJson('/api/v1/cibil/reports', cibilApiPayload($this->customer))->assertForbidden();

    $foreignSession = JourneySession::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    $this->actingAs($this->officer)->postJson('/api/v1/cibil/reports', cibilApiPayload($this->customer, ['journey_session_id' => $foreignSession->public_id]))
        ->assertStatus(422)->assertJson(['error_code' => 'application_mismatch']);

    expect(CibilCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('refuses to show a request to staff who may not see its customer, however its id was obtained', function () {
    $request = fixtureCibilRequest($this->customer);
    $noCustomerAccess = cibilUserWith(['ViewAny:CibilCreditRequest', 'View:CibilCreditRequest', 'ViewReport:CibilCreditRequest']);

    $this->actingAs($noCustomerAccess)->getJson("/api/v1/cibil/reports/{$request->public_id}?include=report")->assertForbidden();
    $this->getJson("/api/v1/cibil/requests/{$request->public_id}/status")->assertForbidden();
    $this->getJson("/api/v1/cibil/customers/{$this->customer->public_id}/reports")->assertForbidden();
    $this->getJson("/api/v1/cibil/reports/{$request->id}")->assertNotFound();
});

it('returns the full report only with ?include=report and the raw response only with ViewRaw', function () {
    $request = fixtureCibilRequest($this->customer);

    $this->actingAs($this->officer)->getJson("/api/v1/cibil/reports/{$request->public_id}")
        ->assertOk()->assertJsonPath('data.summary.accounts_total', null)->assertJsonPath('data.provider_client_id', $request->provider_client_id)->assertJsonMissingPath('data.report');

    $report = $this->getJson("/api/v1/cibil/reports/{$request->public_id}?include=report,raw")
        ->assertOk()
        ->assertJsonPath('data.report.header.control_number', '9100000001')
        ->assertJsonPath('data.report.identities.PAN (01).0', 'XXXXXX999Z')
        ->assertJsonPath('data.report.accounts.0.fields.Account type', 'Credit card (10)')
        ->assertJsonPath('data.report.scores.0.reasons', ['14', '02'])
        ->assertJsonMissingPath('data.raw');
    expect($report->getContent())->not->toContain('AAAPZ9999Z')->not->toContain('9000000001');

    $reviewer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $reviewer->givePermissionTo('ViewRaw:CibilCreditRequest');
    $this->actingAs($reviewer)->getJson("/api/v1/cibil/reports/{$request->public_id}?include=raw")
        ->assertOk()->assertJsonPath('data.raw.data.pan', 'AAAPZ9999Z');

    expect($request->auditLogs()->pluck('action')->all())->toContain('viewed', 'raw_viewed');
});

it('withholds the report from a role that may only see scores', function () {
    $request = fixtureCibilRequest($this->customer);
    $scoresOnly = cibilUserWith(['ViewAny:CibilCreditRequest', 'View:CibilCreditRequest', 'View:Customer']);

    $this->actingAs($scoresOnly)->getJson("/api/v1/cibil/reports/{$request->public_id}?include=report")
        ->assertOk()->assertJsonPath('data.score', 750)->assertJsonMissingPath('data.report')->assertJsonMissingPath('data.summary');
});

it('lists a customer\'s requests and reports a request\'s status', function () {
    $mine = fixtureCibilRequest($this->customer);
    CibilCreditRequest::factory()->successful()->create();

    $this->actingAs($this->officer)->getJson("/api/v1/cibil/customers/{$this->customer->public_id}/reports")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $mine->public_id)->assertJson(['success' => true]);

    $this->getJson("/api/v1/cibil/requests/{$mine->public_id}/status")
        ->assertOk()->assertJson(['data' => ['status' => 'success', 'score' => 750, 'product' => 'report', 'pdf_available' => false]]);

    $this->getJson('/api/v1/cibil/requests/'.Str::uuid().'/status')->assertNotFound();
});

it('serves a stored PDF from private storage without calling Surepass, to permitted staff only', function () {
    Storage::fake(FetchCibilPdf::DISK);
    fakeSurepassCibil();
    $pdf = fixtureCibilRequest($this->customer, ['raw_response' => cibilFixture('pdf'), 'pdf_path' => 'cibil-reports/fixture.pdf.enc'], fn ($factory) => $factory->pdf());
    Storage::disk(FetchCibilPdf::DISK)->put('cibil-reports/fixture.pdf.enc', Crypt::encryptString('%PDF-1.4 stored CIBIL fixture'));

    $this->actingAs($this->officer)->post("/api/v1/cibil/reports/{$pdf->public_id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertContent('%PDF-1.4 stored CIBIL fixture');

    $noPdf = cibilUserWith(['ViewAny:CibilCreditRequest', 'View:CibilCreditRequest', 'View:Customer']);
    $this->actingAs($noPdf)->postJson("/api/v1/cibil/reports/{$pdf->public_id}/pdf")->assertForbidden();

    Http::assertNothingSent();
    expect($pdf->auditLogs()->pluck('action')->all())->toContain('pdf_downloaded');
});

it('starts one paid PDF request from a report, then serves the stored PDF instead of paying again', function () {
    Storage::fake(FetchCibilPdf::DISK);
    fakeSurepassCibil();
    $report = fixtureCibilRequest($this->customer);

    $this->actingAs($this->officer)->postJson("/api/v1/cibil/reports/{$report->public_id}/pdf")
        ->assertStatus(202)->assertJson(['outcome' => 'queued', 'data' => ['product' => 'pdf']]);

    $pdf = CibilCreditRequest::query()->where('product', CibilProduct::Pdf)->sole();
    expect($pdf->hasStoredPdf())->toBeTrue()->and($pdf->credit_consent_id)->toBe($report->credit_consent_id);

    $this->post("/api/v1/cibil/reports/{$report->public_id}/pdf")
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-CIBIL-Request-Id', $pdf->public_id);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-cibil/fetch-report-pdf'));
});

it('refuses a PDF whose link has expired instead of paying for it again', function () {
    Storage::fake(FetchCibilPdf::DISK);
    Http::fake(['files.surepass.test/*' => Http::response('gone', 410)]);
    fakeSurepassCibil();
    $pdf = fixtureCibilRequest($this->customer, ['raw_response' => cibilFixture('pdf')], fn ($factory) => $factory->pdf());

    $this->actingAs($this->officer)->postJson("/api/v1/cibil/reports/{$pdf->public_id}/pdf")
        ->assertStatus(422)->assertJson(['success' => false, 'error_code' => 'request_refused']);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'credit-report-cibil'));
    expect($pdf->refresh()->pdf_error_code)->toBe('pdf_unavailable');
});

it('rate limits each employee to 30 calls a minute', function () {
    $request = fixtureCibilRequest($this->customer);
    $this->actingAs($this->officer);

    foreach (range(1, 30) as $ignored) {
        $this->getJson("/api/v1/cibil/requests/{$request->public_id}/status")->assertOk();
    }

    $this->getJson("/api/v1/cibil/requests/{$request->public_id}/status")->assertStatus(429);
});
