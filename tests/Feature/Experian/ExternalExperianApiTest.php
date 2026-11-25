<?php

use App\Models\ApiClient;
use App\Models\User;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianRoles;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The external Experian API (routes/external.php) — the same door as the
 * external CIBIL API, with the caller choosing the bureau by URL. Surepass is
 * faked; an API client's key signs in as its service account.
 */
beforeEach(function () {
    CrifRoles::grant();
    ExperianRoles::grant();
});

/**
 * @return array{0: ApiClient, 1: array<string, string>}
 */
function externalExperianClient(string $role = CrifRoles::OFFICER_ROLE, ?Closure $state = null): array
{
    $client = ($state ? $state(ApiClient::factory()) : ApiClient::factory())->create();
    $client->user->syncRoles([$role]);

    return [$client, ['Authorization' => 'Bearer '.$client->issueKey()]];
}

function externalExperianPayload(array $overrides = []): array
{
    return [
        'name' => 'Test  Fixture',
        'mobile' => '+91 90000 00001',
        'pan' => 'aaapz9999z',
        'consent' => [
            'method' => 'recorded_call',
            'evidence_reference' => 'LMS-CALL-001',
            'consented_at' => now()->subHour()->toIso8601String(),
            'attested' => true,
        ],
        ...$overrides,
    ];
}

it('answers 401 JSON to a caller without a working API key, before any paid call', function (Closure $headers) {
    fakeSurepassExperian();

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers())
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);

    expect(Customer::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'no key' => [fn () => []],
    'unknown key' => [fn () => ['Authorization' => 'Bearer '.ApiClient::KEY_PREFIX.Str::random(48)]],
    'revoked key' => [function () {
        [$client, $headers] = externalExperianClient();
        $client->revoke();

        return $headers;
    }],
    'staff session instead of a key' => [function () {
        test()->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]));

        return [];
    }],
]);

it('accepts a key only from the addresses its client allows', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient(state: fn ($factory) => $factory->allowingOnly(['203.0.113.0/24']));

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers)->assertUnauthorized();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers)
        ->assertStatus(202);
});

it('requests a full Experian report for a new person: opens the customer, queues one paid call and answers 202', function () {
    fakeSurepassExperian();
    [$client, $headers] = externalExperianClient();

    $response = $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers);

    $customer = Customer::query()->sole();
    $response->assertStatus(202)
        ->assertJson(['success' => true, 'outcome' => 'queued', 'data' => ['product' => 'report', 'pan_masked' => 'XXXXXX999Z', 'mobile_masked' => '******0001', 'customer_id' => $customer->public_id]])
        ->assertJsonMissingPath('data.raw');
    expect($response->getContent())->not->toContain('AAAPZ9999Z')->not->toContain('9000000001');

    expect($customer)
        ->full_name->toBe('Test Fixture')
        ->phone->toBe('9000000001')
        ->pan_masked->toBe('XXXXXX999Z')
        ->gender->toBeNull()
        ->verified_mobile->toBeNull();
    expect(ExperianCreditRequest::query()->sole())
        ->customer_id->toBe($customer->id)
        ->channel->toBe('external')
        ->requested_by->toBe($client->user_id);
    expect(CreditConsent::query()->sole())
        ->recorded_by->toBe($client->user_id)
        ->evidence_reference->toBe('LMS-CALL-001');
    expect(CibilCreditRequest::query()->count())->toBe(0);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-experian/fetch-report')
        && $request->data() === ['name' => 'Test Fixture', 'pan' => 'AAAPZ9999Z', 'mobile' => '9000000001', 'consent' => 'Y']);
});

it('lets the same client choose CIBIL or Experian for the same person, as one customer', function () {
    CibilRoles::grant();
    fakeSurepassCibil();
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers)->assertStatus(202);
    $this->postJson('/api/v1/external/cibil/reports', externalExperianPayload(['gender' => 'female']), $headers)->assertStatus(202);

    $customer = Customer::query()->sole();
    expect(ExperianCreditRequest::query()->sole()->customer_id)->toBe($customer->id)
        ->and(CibilCreditRequest::query()->sole()->customer_id)->toBe($customer->id);
});

it('links the request to the existing customer with that mobile instead of opening another', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();
    $existing = Customer::factory()->create(['full_name' => 'Name On File', 'phone' => '9000000001']);

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers)->assertStatus(202);

    expect(Customer::query()->count())->toBe(1)
        ->and(ExperianCreditRequest::query()->sole()->customer_id)->toBe($existing->id)
        ->and($existing->refresh()->full_name)->toBe('Name On File');
});

it('opens a new customer when the customer with that mobile has a different PAN on file', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();
    $samePhone = tap(Customer::factory()->make(['phone' => '9000000001']))->setPan('BBBPZ1111Z');
    $samePhone->save();

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers)->assertStatus(202);

    expect(ExperianCreditRequest::query()->sole()->customer_id)->not->toBe($samePhone->id)
        ->and(Customer::query()->count())->toBe(2);
});

it('never links to a customer named by a customer_id in the payload', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();
    $someoneElse = Customer::factory()->create(['phone' => '9888888888']);

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(['customer_id' => $someoneElse->public_id]), $headers)->assertStatus(202);

    expect(ExperianCreditRequest::query()->sole()->customer_id)->not->toBe($someoneElse->id);
});

it('refuses a client whose role may not request Experian (403) without opening a customer', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient('Marketing');

    $this->postJson('/api/v1/external/experian/reports', externalExperianPayload(), $headers)->assertForbidden();

    expect(Customer::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('refuses a fresh pull (403) to a client without FetchFresh', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();

    $this->postJson('/api/v1/external/experian/score', externalExperianPayload(['fresh' => true, 'fresh_reason' => 'Customer disputes the last report']), $headers)
        ->assertForbidden();

    Http::assertNothingSent();
});

it('requires the person\'s details and the consent, but no gender, with the standard envelope (422)', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();

    $response = $this->postJson('/api/v1/external/experian/reports', [], $headers)
        ->assertStatus(422)
        ->assertJson(['success' => false, 'error_code' => 'validation_failed'])
        ->assertJsonValidationErrors([
            'name' => 'Enter the customer\'s full name as on the PAN card.',
            'mobile' => 'Enter the customer\'s 10-digit Indian mobile number.',
            'pan',
            'consent.method' => 'Record the customer\'s consent: consent.method, evidence_reference, consented_at and attested.',
        ]);

    $response->assertJsonMissingValidationErrors('gender');
    expect(Customer::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('answers 200 with a reusable result, then lets the client read its status and report', function () {
    fakeSurepassExperian();
    [, $headers] = externalExperianClient();
    $existing = ExperianCreditRequest::factory()->forPan('AAAPZ9999Z')->successful(experianFixture(), 792)->create([
        'customer_id' => Customer::factory()->create(['phone' => '9000000001'])->id,
    ]);

    $this->postJson('/api/v1/external/experian/score', externalExperianPayload(), $headers)
        ->assertOk()->assertJson(['outcome' => 'reused', 'data' => ['id' => (string) $existing->public_id, 'score' => 792]]);

    $this->getJson("/api/v1/external/experian/requests/{$existing->public_id}/status", $headers)
        ->assertOk()->assertJson(['data' => ['status' => 'success', 'score' => 792]]);

    $this->getJson("/api/v1/external/experian/reports/{$existing->public_id}?include=report", $headers)
        ->assertOk()->assertJsonPath('data.report.header.report_number', '1760600000000');

    Http::assertNothingSent();
    expect($existing->auditLogs()->where('action', 'viewed')->sole()->changes)->toBe(['channel' => ['old' => null, 'new' => 'external']]);
});

it('keeps the internal Experian API recording its own channel', function () {
    fakeSurepassExperian();
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $request = ExperianCreditRequest::factory()->successful(experianFixture(), 792)->create(['customer_id' => Customer::factory()->create()->id]);

    $this->actingAs($officer)->getJson("/api/v1/experian/reports/{$request->public_id}?include=report")->assertOk();

    expect($request->auditLogs()->where('action', 'viewed')->sole()->changes)->toBe(['channel' => ['old' => null, 'new' => 'api']]);
});

it('rate limits each API client to 60 Experian calls a minute', function () {
    [, $headers] = externalExperianClient();
    $request = ExperianCreditRequest::factory()->successful(experianFixture(), 792)->create(['customer_id' => Customer::factory()->create()->id]);

    foreach (range(1, 60) as $ignored) {
        $this->getJson("/api/v1/external/experian/requests/{$request->public_id}/status", $headers)->assertOk();
    }

    $this->getJson("/api/v1/external/experian/requests/{$request->public_id}/status", $headers)->assertStatus(429);
});
