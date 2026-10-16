<?php

use App\Models\User;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxFingerprint;
use App\Modules\Equifax\Services\EquifaxRoles;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    CrifRoles::grant();
    EquifaxRoles::grant();
    $this->officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $this->customer = Customer::factory()->create(['full_name' => 'Test Fixture', 'phone' => '9000000001']);
});

function equifaxApiPayload(Customer $customer, array $overrides = []): array
{
    return [
        'customer_id' => $customer->public_id,
        'pan' => 'aaapz9999z',
        'gender' => 'Male',
        'consent' => [
            'method' => 'recorded_call',
            'evidence_reference' => 'CALL-API-001',
            'consented_at' => now()->subHour()->toIso8601String(),
            'attested' => true,
        ],
        ...$overrides,
    ];
}

function fixtureEquifaxRequest(Customer $customer, array $attributes = []): EquifaxCreditRequest
{
    return EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->successful(equifaxReportFixture())->create([
        'customer_id' => $customer->id,
        'mobile' => '9000000001',
        'mobile_masked' => '******0001',
        ...$attributes,
    ]);
}

it('answers a signed-out caller with 401 JSON, never a redirect', function (string $method, string $path) {
    $this->json($method, $path)->assertUnauthorized();
    $this->{$method === 'GET' ? 'get' : 'post'}($path)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
})->with([
    ['POST', '/api/v1/equifax/reports'],
    ['GET', '/api/v1/equifax/requests/'.Str::uuid().'/status'],
]);

it('requests a full report, defaulting name and mobile to the customer, and returns 202 with a masked request', function () {
    fakeSurepassEquifax();

    $response = $this->actingAs($this->officer)->postJson('/api/v1/equifax/reports', equifaxApiPayload($this->customer));

    $response->assertStatus(202)
        ->assertJson(['success' => true, 'outcome' => 'queued', 'data' => ['product' => 'report', 'pan_masked' => 'XXXXXX999Z', 'mobile_masked' => '******0001', 'customer_id' => $this->customer->public_id]])
        ->assertJsonMissingPath('data.raw')
        ->assertJsonMissingPath('data.report');

    expect($response->getContent())->not->toContain('AAAPZ9999Z')->not->toContain('9000000001')->not->toContain('test-surepass-token')
        ->and(EquifaxCreditRequest::query()->sole())->full_name->toBe('Test Fixture')->channel->toBe('api');
});

it('answers 200 for a reused result and 409 for an equivalent request in flight', function () {
    fakeSurepassEquifax();
    $existing = fixtureEquifaxRequest($this->customer);

    $this->actingAs($this->officer)->postJson('/api/v1/equifax/score', equifaxApiPayload($this->customer))
        ->assertOk()->assertJson(['outcome' => 'reused', 'data' => ['id' => (string) $existing->public_id, 'score' => 761]]);

    $existing->forceFill(['status' => 'processing', 'in_flight_key' => EquifaxFingerprint::inFlight($existing->pan_hash, 'report')])->save();

    $this->postJson('/api/v1/equifax/reports', equifaxApiPayload($this->customer))
        ->assertStatus(409)->assertJson(['success' => false, 'outcome' => 'blocked']);
    Http::assertNothingSent();
});

it('validates input with the standard envelope', function (array $overrides, string $field) {
    fakeSurepassEquifax();

    $this->actingAs($this->officer)->postJson('/api/v1/equifax/reports', equifaxApiPayload($this->customer, $overrides))
        ->assertStatus(422)
        ->assertJson(['success' => false, 'error_code' => 'validation_failed'])
        ->assertJsonValidationErrors($field);
})->with([
    'bad PAN' => [['pan' => 'ABCD'], 'pan'],
    'unsupported gender' => [['gender' => 'other'], 'gender'],
    'unknown customer' => [['customer_id' => (string) Str::uuid()], 'customer_id'],
    'consent missing' => [['consent' => ['attested' => true]], 'consent.method'],
    'fresh without reason' => [['fresh' => true], 'fresh_reason'],
]);

it('refuses a request from staff without Equifax permission (403) and for another customer\'s application (422)', function () {
    fakeSurepassEquifax();
    $marketing = tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']);

    $this->actingAs($marketing)->postJson('/api/v1/equifax/reports', equifaxApiPayload($this->customer))->assertForbidden();

    $foreignSession = JourneySession::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    $this->actingAs($this->officer)->postJson('/api/v1/equifax/reports', equifaxApiPayload($this->customer, ['journey_session_id' => $foreignSession->public_id]))
        ->assertStatus(422)->assertJson(['error_code' => 'application_mismatch']);

    expect(EquifaxCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('returns the full report only with ?include=report and the raw response only with ViewRaw', function () {
    $request = fixtureEquifaxRequest($this->customer);

    $this->actingAs($this->officer)->getJson("/api/v1/equifax/reports/{$request->public_id}")
        ->assertOk()->assertJsonPath('data.summary.accounts_total', null)->assertJsonMissingPath('data.report');

    $report = $this->getJson("/api/v1/equifax/reports/{$request->public_id}?include=report,raw")
        ->assertOk()
        ->assertJsonPath('data.report.header.order_number', 'FIXTURE-ORDER-0001')
        ->assertJsonPath('data.report.identities.PAN id.0.number', 'XXXXXX999Z')
        ->assertJsonMissingPath('data.raw');
    expect($report->getContent())->not->toContain('AAAPZ9999Z');

    $reviewer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $reviewer->givePermissionTo('ViewRaw:EquifaxCreditRequest');
    $this->actingAs($reviewer)->getJson("/api/v1/equifax/reports/{$request->public_id}?include=raw")
        ->assertOk()->assertJsonPath('data.raw.data.id_number', 'AAAPZ9999Z');

    expect($request->auditLogs()->pluck('action')->all())->toContain('viewed', 'raw_viewed');
});

it('withholds the report from a role that may only see scores', function () {
    $request = fixtureEquifaxRequest($this->customer);
    $scoresOnly = User::factory()->create(['is_admin' => true]);
    $scoresOnly->syncRoles([]);
    $scoresOnly->givePermissionTo(['ViewAny:EquifaxCreditRequest', 'View:EquifaxCreditRequest', 'View:Customer']);

    $this->actingAs($scoresOnly)->getJson("/api/v1/equifax/reports/{$request->public_id}?include=report")
        ->assertOk()->assertJsonPath('data.score', 761)->assertJsonMissingPath('data.report')->assertJsonMissingPath('data.summary');
});

it('lists a customer\'s requests and reports a request\'s status', function () {
    $mine = fixtureEquifaxRequest($this->customer);
    EquifaxCreditRequest::factory()->successful()->create();

    $this->actingAs($this->officer)->getJson("/api/v1/equifax/customers/{$this->customer->public_id}/reports")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $mine->public_id)->assertJson(['success' => true]);

    $this->getJson("/api/v1/equifax/requests/{$mine->public_id}/status")
        ->assertOk()->assertJson(['data' => ['status' => 'success', 'score' => 761, 'product' => 'report']]);

    $this->getJson('/api/v1/equifax/requests/'.Str::uuid().'/status')->assertNotFound();
});

it('serves the PDF from the report link to permitted staff only', function () {
    fakeSurepassEquifax();
    Http::fake(['files.example.test/*' => Http::response('%PDF-1.7 equifax fixture', 200)]);
    Storage::fake('local');
    $body = equifaxReportFixture();
    $body['data']['credit_report_link'] = 'https://files.example.test/eq.pdf';
    $request = fixtureEquifaxRequest($this->customer, ['raw_response' => $body]);

    $this->actingAs($this->officer)->post("/api/v1/equifax/reports/{$request->public_id}/pdf")
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $noPdf = User::factory()->create(['is_admin' => true]);
    $noPdf->syncRoles([]);
    $noPdf->givePermissionTo(['ViewAny:EquifaxCreditRequest', 'View:EquifaxCreditRequest', 'View:Customer']);
    $this->actingAs($noPdf)->postJson("/api/v1/equifax/reports/{$request->public_id}/pdf")->assertForbidden();

    Http::assertNotSent(fn ($sent) => str_contains($sent->url(), 'credit-report-v2'));
});

it('rate limits each employee to 30 calls a minute', function () {
    $request = fixtureEquifaxRequest($this->customer);
    $this->actingAs($this->officer);

    foreach (range(1, 30) as $ignored) {
        $this->getJson("/api/v1/equifax/requests/{$request->public_id}/status")->assertOk();
    }

    $this->getJson("/api/v1/equifax/requests/{$request->public_id}/status")->assertStatus(429);
});
