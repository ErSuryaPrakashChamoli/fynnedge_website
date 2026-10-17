<?php

use App\Filament\Resources\ApiClients\Pages\CreateApiClient;
use App\Filament\Resources\ApiClients\Pages\EditApiClient;
use App\Filament\Resources\ApiClients\Pages\ListApiClients;
use App\Models\ApiClient;
use App\Models\User;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\CreditBureau\Services\CrifRoles;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    CrifRoles::grant();
    CibilRoles::grant();
});

function officerApiClient(): ApiClient
{
    return tap(ApiClient::factory()->create(), fn (ApiClient $client) => $client->user->syncRoles([CrifRoles::OFFICER_ROLE]));
}

/**
 * The key from the one notification that shows it — read before
 * assertNotified(), which takes the notifications out of the session.
 */
function issuedApiKey(): string
{
    // After a Livewire action Filament has already moved them to claimed_notifications.
    $body = collect([...session('filament.notifications', []), ...session('filament.claimed_notifications', [])])->pluck('body')->implode(' ');
    preg_match('/'.ApiClient::KEY_PREFIX.'[A-Za-z0-9]{48}/', $body, $match);

    return $match[0] ?? throw new RuntimeException('No API key was shown.');
}

function apiClientStatus(string $key): int
{
    return test()->getJson('/api/v1/external/cibil/requests/'.Str::uuid().'/status', ['Authorization' => 'Bearer '.$key])->status();
}

it('creates a client with its own service account, keeps only a hash and shows the working key once', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(CreateApiClient::class)
        ->fillForm(['name' => 'FynnEdge LMS', 'role' => CrifRoles::OFFICER_ROLE, 'allowed_ips' => ['127.0.0.1', '203.0.113.0/24']])
        ->call('create')
        ->assertHasNoFormErrors();

    $key = issuedApiKey();
    $page->assertNotified('API key for FynnEdge LMS');
    $client = ApiClient::query()->sole();
    expect($client)
        ->key_hash->toBe(ApiClient::hashKey($key))
        ->key_prefix->toBe(substr($key, 0, 12))
        ->allowed_ips->toBe(['127.0.0.1', '203.0.113.0/24'])
        ->and($client->user)
        ->name->toBe('FynnEdge LMS (API client)')
        ->is_admin->toBeFalse()
        ->email->toEndWith('@'.ApiClient::SERVICE_ACCOUNT_DOMAIN)
        ->and($client->user->getRoleNames()->all())->toBe([CrifRoles::OFFICER_ROLE])
        ->and(json_encode(ApiClient::query()->toBase()->first()))->not->toContain($key);

    // A real record id is unknown here, so a working key gets 404, a refused one 401.
    expect(apiClientStatus($key))->toBe(404);
});

it('refuses the super admin role and anything that is not an IP address or range', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateApiClient::class)
        ->fillForm(['name' => 'FynnEdge LMS', 'role' => 'super_admin', 'allowed_ips' => ['203.0.113.0/40']])
        ->call('create')
        ->assertHasFormErrors(['role', 'allowed_ips.0']);

    expect(ApiClient::query()->count())->toBe(0);
});

it('replaces the key: the old one stops working at once and the new one is shown', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $client = officerApiClient();
    $oldKey = $client->issueKey();

    $page = Livewire::test(EditApiClient::class, ['record' => $client->getRouteKey()])
        ->callAction('generateKey');

    $newKey = issuedApiKey();
    $page->assertNotified('API key for '.$client->name);
    expect(apiClientStatus($oldKey))->toBe(401)
        ->and(apiClientStatus($newKey))->toBe(404);
});

it('revokes a client so its key is refused', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $client = officerApiClient();
    $key = $client->issueKey();

    Livewire::test(EditApiClient::class, ['record' => $client->getRouteKey()])
        ->callAction('revoke');

    expect($client->refresh()->isActive())->toBeFalse()
        ->and(apiClientStatus($key))->toBe(401);
});

it('changes the service account\'s role and name with the client', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $client = officerApiClient();

    Livewire::test(EditApiClient::class, ['record' => $client->getRouteKey()])
        ->assertSchemaStateSet(['role' => CrifRoles::OFFICER_ROLE])
        ->fillForm(['name' => 'Partner LMS', 'role' => 'Marketing'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->user->refresh())
        ->name->toBe('Partner LMS (API client)')
        ->and($client->user->getRoleNames()->all())->toBe(['Marketing']);
});

it('lists clients with their masked key, never the key hash', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $client = officerApiClient();

    Livewire::test(ListApiClients::class)
        ->assertCanSeeTableRecords([$client])
        ->assertSee($client->maskedKey())
        ->assertDontSee($client->key_hash);
});

it('keeps API clients away from an admin without the permission', function () {
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);

    $this->actingAs($officer)->get('/admin/api-clients')->assertForbidden();
});
